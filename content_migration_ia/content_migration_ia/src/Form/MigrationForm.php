<?php


namespace Drupal\content_migration_ia\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\taxonomy\Entity\Term;
use Drupal\Core\Url;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\media\Entity\Media;

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
	 * {@inheritdoc}
	 */
	public function buildForm(array $form, FormStateInterface $form_state)
	{

		$content_type_storage = $this->entityTypeManager->getStorage('node_type');
		$content_type_entities = $content_type_storage->loadMultiple();

		$content_types = ['' => "Select Content Type"];
		foreach ($content_type_entities as $content_type) {
			$content_types[$content_type->id()] = $content_type->label();
		}
		if (isset($_GET['debug'])) {
			die(dump($content_types));
		}

		$form['contenttype'] = [
			'#type' => "select",
			'#title' => 'Content Type',
			'#required' => true,
			'#description' => 'Select content type where you want to import the csv data',
			'#options' => $content_types,
		];

		$form["#attributes"] = ['enctype' => 'multipart/form-data'];


		$form['submit'] = [
			'#type' => "submit",
			"#value" => "Export",
		];


		return $form;
	}

	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state)
	{

		$content_type = $form_state->getValue('contenttype', '');

		$msgObj = $this->msgObj;

		//echo "<pre>"; print_r($_POST); exit;

		if ($content_type == "") {
			$msgObj->addError(t("Content type is not available"));
		} else {

			$nodes = $this->entityTypeManager->getStorage('node')
				->loadByProperties(['type' => $content_type]);

      if(!empty($nodes))
      {
			  $output = [];
			  foreach ($nodes as $node) {
				  $output[] = $this->prepareNodeData($node);

			  }
      }else{
        
        $field_definitions = \Drupal::entityTypeManager()->getStorage('field_config')->loadByProperties(['bundle' => $content_type]);

        $output[0]["nid"] = '0';
        $output[0]["langcode"] = 'en';
        $output[0]["title"] = '';
        $output[0]["path"] = '';
        foreach($field_definitions as $field_definition)
        {
          if ($field_definition instanceof \Drupal\field\Entity\FieldConfig) {
               $field_name = $field_definition->getName();
               $output[0][$field_name] =  "";
          }
          
        }

      }

      
			//echo "Output: ";
			//die(dump($output));

			$filename = $content_type . ".csv";
			$this->arrayToCsv($output, $filename);
		}


		$messages = $msgObj->all();

		if (!isset($messages['error'])) {
			$msgObj->addMessage(t("Data exported successfully !!"));
		}
	}

	public function arrayToCsv($data, $filename = 'output.csv')
	{
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


	public function prepareNodeData($node)
	{
		$data = $node->toArray();

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
			if ($node->hasField($field_name)) {
				$field_definition = $node->getFieldDefinition($field_name);
				$field_type = $field_definition->getType();
				$settings = $field_definition->getSettings();
				$target_entity_type = $settings['target_type'];
				$finalVal = [];
				if ($field_type == "entity_reference") {
					$tags = $node->get($field_name)->referencedEntities();
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
					$values = $node->{$field_name}->getValue();
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
