<?php


namespace Drupal\blocks_migration_ia\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Vocabulary;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\Media;
use Drupal\block\Entity\BlockContentType;
use Drupal\block_content\Entity\BlockContent;

/**
 * Migrate CSV form
 */

class MigrationForm extends FormBase
{

	protected $msgObj, $filesystem, $database, $languageManager;
	protected $entityTypeManager, $file_url_generator;

	public function __construct()
	{
		$this->msgObj = \Drupal::messenger();
		$this->filesystem = \Drupal::service("file_system");
		$this->database = \Drupal::database();
		$this->entityTypeManager = \Drupal::service('entity_type.manager');
		$this->file_url_generator = \Drupal::service('file_url_generator');
		$this->languageManager = \Drupal::service("language_manager");
	}


	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return 'migration_form';
	}

	/**
 * @param array $form
 * @param \Drupal\Core\Form\FormStateInterface $form_state
 * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
 */
	public function buildForm(array $form, FormStateInterface $form_state) {
      $block_type_storage = $this->entityTypeManager->getStorage('block_content_type');
      $block_types = $block_type_storage->loadMultiple();
  
      $block_type_options = ['' => $this->t('Select Block Type')];
      foreach ($block_types as $block_type) {
        $block_type_options[$block_type->id()] = $block_type->label();
      }

      $form['block_type'] = [
        '#type' => 'select',
        '#title' => $this->t('Block Type'),
        '#options' => $block_type_options,
        '#required' => TRUE,
        '#description' => $this->t('Select block type from which you want to export the CSV data.'),
      ];

      $form['submit'] = [
        '#type' => 'submit',
        '#value' => $this->t('Export'),
      ];

      return $form;
  }

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state) {
     $block_type_id = $form_state->getValue('block_type');

      if (empty($block_type_id)) {
        $this->messenger()->addError($this->t("Block Type is not available"));
        return;
      }

      $block_storage = $this->entityTypeManager->getStorage('block_content');
      $query = $block_storage->getQuery();
      $query->accessCheck(FALSE);
      $query->condition('type', $block_type_id);

      $block_ids = $query->execute();

      if (empty($block_ids)) {
        $this->messenger()->addError($this->t("No blocks found in the selected block type"));
        return;
      }

      $blocks = $block_storage->loadMultiple($block_ids);

      //die(dump($blocks));

      $output = [];
      foreach ($blocks as $block) {
        $output[] = $this->prepareBlockData($block);
      }

      $filename = $block_type_id . "_blocks.csv";
      $this->arrayToCsv($output, $filename);

      $this->messenger()->addMessage($this->t("Data exported successfully !!"));
  }

	public function arrayToCsv($data, $filename = 'output.csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // Write the headings from array keys.
    fputcsv($output, array_keys(reset($data)));

    // Write the data rows.
    foreach ($data as $row) {
      fputcsv($output, $row);
    }

    fclose($output);
    exit;
  }

  public function prepareTermDataNew($block) {
    die(dump($block));
    $block_data = [
      'tid' => $block->tid,
      'name' => $block->name,
      'description' => $block->description,
      'parent' => $block->depth > 1 ? $block->parents[array_keys($block->parents)[0]] : '',
    ];

    return $block_data;
  }

	public function prepareBlockData($block)
	{
    
    //$block = BlockContent::load($block);
    //die(dump($block));
    $data = $block->toArray();


    $search = ["uuid", "vid", "type", "status", "uid", "created", "changed", "promote", "sticky", "default_langcode", "menu_link", "comment", "content_translation_source", "content_translation_outdated"];

		$finalData = [];
		$i = 0;

		foreach ($data as $field_name => &$field) {

			if (stristr($field_name, "revision")) {
				unset($data[$field_name]);
				continue;
			} else if (in_array($field_name, $search)) {
				unset($data[$field_name]);
				continue;
			}
			//echo $field_name."<br/>";
			if ($block->hasField($field_name)) {
				$field_definition = $block->getFieldDefinition($field_name);
				$field_type = $field_definition->getType();
				$settings = $field_definition->getSettings();
				$target_entity_type = $settings['target_type'];
				$finalVal = [];
				if ($field_type == "entity_reference") {
					$tags = $block->get($field_name)->referencedEntities();
					if ($target_entity_type == "media") {
						foreach ($tags as $media_item) {
							// Load the referenced media entity.
							$media = Media::load($media_item->id());

							if ($media) {
								$media_type = $media->bundle(); // Get the media type machine name.
								$file_url = '';
								if ($media_type == 'image') {
									$file_url = $media->field_media_image->entity->createFileUrl();
								} elseif ($media_type == 'document') {
									$file_url = $media->field_media_document->entity->createFileUrl();
								} elseif ($media_type == 'video') {
									//die(dump($media));
									$video_file = $media->get('field_media_video_file')->entity;
									if ($video_file instanceof File) {
										$file_url = $media->field_media_video_file->entity->createFileUrl();
									} else {
										$file_url = $media->get('field_media_video_file')->getString();
									}
								} else if ($media_type == "remote_video") {
									$file_url = $media->get('field_media_oembed_video')->getString();
								} else if ($media_type == "audio") {
									$file_url = $media->field_media_audio->entity->createFileUrl();
								}

								$finalVal[] = $media_type . "::::" . $file_url;
							}
						}
					} else {
						foreach ($tags as $tag) {
							$finalVal[] = $tag->label();
						}
					}
				} else {
					$values = $block->{$field_name}->getValue();
					foreach ($values as $value) {
						if ($field_type == "image" || $field_type == 'file') {
							$file = \Drupal\file\Entity\File::load($value['target_id']);
							if ($file) {
								$url = $this->file_url_generator->generateAbsoluteString($file->getFileUri());

								$finalVal[] = $url;
							}
						} else if ($field_type == "path") {
							$finalVal[] = isset($value["alias"]) ? $value["alias"] : '';
						} else if ($field_type == "metatag_computed" && 0) {
							//die(dump($values));
							$finalVal[] = $value["attributes"]["name"] . ":" . $value["attributes"]["content"];

						} else if ($field_type == "float") {
							$finalVal[] = (float) $value["value"];
						} else {
							$finalVal[] = $value["value"];
						}
					}
				}

				//$finalData[$field_name." -- ".$field_type] = implode("<SEPERATOR>",$finalVal);
				$finalData[$field_name] = implode("<SEPERATOR>", $finalVal);

			}
			$i++;
		}
    if(isset($finalData["field_meta_tags"]))
    {
      unset($finalData["metatag"]);
    }
    //print_r($finalData); exit;
		return $finalData;
	}

}
