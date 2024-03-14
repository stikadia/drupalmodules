<?php


namespace Drupal\content_migration_ia\Form;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;
use Drupal\media\Entity\Media;
use Drupal\node\Entity\Node;
use Drupal\path_alias\Entity\PathAlias;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Site\Settings;

/**
 * Migrate CSV form
 */

class ImportForm extends FormBase
{

	protected $msgObj, $filesystem, $database, $allowlangs;
	protected $entityTypeManager, $file_url_generator;

	public function __construct()
	{
		$this->msgObj = \Drupal::messenger();
		$this->filesystem = \Drupal::service("file_system");
		$this->database = \Drupal::database();
		$this->entityTypeManager = \Drupal::service('entity_type.manager');
		$this->file_url_generator = \Drupal::service('file_url_generator');
		$languageManager = \Drupal::service("language_manager");

		$allowlangs = [];
		$languages = $languageManager->getLanguages();

		foreach ($languages as $language) {
			$allowlangs[] = $language->getId();
		}
		$this->allowlangs = $allowlangs;
	}


	/**
	 * {@inheritdoc}
	 */
	public function getFormId()
	{
		return 'import_form';
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
		$form['filename'] = [
			'#type' => "managed_file",
			//'#type' => "file",
			"#title" => $this->t("Upload CSV File"),
			//"#required" => TRUE,
			"#description" => "eg., Select Blog CSV file and click on upload to save data in blog content type of Drupal.",
			'#upload_location' => 'public://importdata',
			'#upload_validators' => [
				'file_validate_extensions' => ['csv'],
			],

			//"#multiple" => true,
			"#accept" => "text/csv",
		];

		$form["#attributes"] = ['enctype' => 'multipart/form-data'];


		$form['submit'] = [
			'#type' => "submit",
			"#value" => "Import",
		];


		return $form;
	}


	/**
	 * {@inheritdoc}
	 */
	public function submitForm(array &$form, FormStateInterface $form_state)
	{

		$form_files = $form_state->getValue('filename');
		$contenttype = $form_state->getValue('contenttype', '');

		$msgObj = $this->msgObj;

		//echo "<pre>"; print_r($_POST); exit;

		foreach ($form_files as $form_file) {
			if (isset($form_file) && !empty($form_file)) {

				$file = File::load($form_file);
				$file_path = $file->getFileUri();
				$path = \Drupal::service('file_url_generator')->generateAbsoluteString($file_path);

				$csv_data = $this->processCSVFile($file_path);
				// Set up the batch process.
				$batch = [
					'title' => t('Importing CSV Data'),
					'operations' => [],
					'init_message' => t('Starting import...'),
					'progress_message' => t('Processed @current out of @total.'),
					'finished' => [$this, 'importFinished'],
				];

				$batch_size = 1; // Adjust the batch size as needed.

				$chunks = array_chunk($csv_data, $batch_size);
				//dump($chunks);
				//dump($csv_data);
				//die();
				foreach ($chunks as $index => $chunk) {
					$batch['operations'][] = [[$this, 'processChunk'], [$chunk, $contenttype, $index]];
				}

				batch_set($batch);

				$file->delete();

			}
		}

		$messages = $msgObj->all();

		if (!isset($messages['error'])) {
			$msgObj->addMessage(t("Data imported successfully !!"));
		}

	}

	public function processChunk($chunk, $contenttype, $index, &$context)
	{
		$msgObj = $this->msgObj;
		$drupalRoot = \Drupal::root() . "/";
		foreach ($chunk as $values) {
			if (isset($values["nid"]) && $values["nid"] > 0) {
				$node = Node::load($values["nid"]);
				if (!($node && $node->bundle() === $contenttype)) {
					$msgObj->addError(t("Node id, you are trying to update is not of selected content type."));
					break;
				}
				if (isset($values["langcode"])) {
					$langcode = $values["langcode"];

					if (!in_array($langcode, $this->allowlangs)) {
						$msgObj->addError(t("Node id, you are trying to update is not valid for langcode: " . $langcode));
						break;
					}

					$node_langcode = $node->language()->getId();

					if ($langcode != $node_langcode) {
						if (!$node->isTranslatable()) {
							$msgObj->addError(t("Node, you are trying to translate is not translatable"));
							break;
						}
						if ($node->hasTranslation($langcode)) {
							$node = $node->getTranslation($langcode);
						} else {
							$node = $node->addTranslation($langcode);
						}
					}
				}
			} else {
				$node = Node::create(['type' => $contenttype]);
			}
			//die(dump($values));
			//die(dump($node));

			$fieldArr = ["path", "metatag"];

			foreach ($values as $field_name => $value) {
				if (in_array($field_name, $fieldArr)) {
					continue;
				}
				if ($node->hasField($field_name)) {

					$field_definition = $node->getFieldDefinition($field_name);
					$field_type = $field_definition->getType();
					//echo $field_name." -- ".$field_type."<hr/>";

					
					$allValues = explode("<SEPERATOR>", $value);

					if ($field_type == "image" || $field_type == 'file') {
						$directory_pattern = $field_definition->getSetting('file_directory');
						$directory = $this->getDirectoryName($directory_pattern);
						$fidArr = [];
						foreach ($allValues as $fileurl) {
							if(trim($fileurl)=='')
							{
								continue;
							}
							$file_data = @file_get_contents($fileurl);
							if (!$file_data) {
								continue;
							}

							$filename = basename($fileurl);

							// Save the image data as a file
							file_put_contents($drupalRoot . "sites/default/files/" . $filename, $file_data);

							if ($directory != '') {
								$destination = 'public://' . $directory . "/" . $filename;
							} else {
								$destination = 'public://' . $filename;
							}

							$this->filesystem->move($drupalRoot . "sites/default/files/" . $filename, $destination, FileSystemInterface::EXISTS_REPLACE);

							// Create a file entity from the downloaded image
							$file = \Drupal::entityTypeManager()->getStorage('file')->create([
								'uri' => $destination,
							]);

							$file->save();
							$fidArr[] = $file->id();
						}
						if (!empty($fidArr)) {
							$node->get($field_name)->setValue($fidArr);
						}
					} else if ($field_type == "entity_reference") {
						$settings = $field_definition->getSettings();
						$target_entity_type = $settings['target_type'];

						if ($target_entity_type == "node") {
							$bundles = $settings["handler_settings"]["target_bundles"];
							$refIds = $this->getNodeIds($value, $bundles);

							$node->get($field_name)->setValue($refIds);
						} else if ($target_entity_type == "taxonomy_term") {
							$bundles = $settings["handler_settings"]["target_bundles"];
							$refIds = $this->getTermIds($value, $bundles);

							$node->get($field_name)->setValue($refIds);
						} else if ($target_entity_type == "user") {
							$refIds = $this->getUserIds($value);

							$node->get($field_name)->setValue($refIds);
						} else if ($target_entity_type == "media") {
							$refIds = $this->process_media_string($value);
							$node->get($field_name)->setValue($refIds);
						}

						//dump($settings);
						//dump($target_entity_type);
						//dump($value);


					} else {
						if ($field_type == "decimal") {
							foreach ($allValues as &$value) {
								$value = floatval($value);
							}
						}
            else if($field_type == "datetime"){
              foreach ($allValues as &$value) {
                $datetime = new DrupalDateTime($value);
                $formatted_datetime = $datetime->format('Y-m-d');
                $value = $formatted_datetime;
                
							}
            }
            else if($field_type == "date"){
              foreach ($allValues as &$value) {
                $datetime = new DrupalDateTime($value);
                $formatted_datetime = $datetime->format('Y-m-d');
                $value = $formatted_datetime;
                
							}
            }
            
            
						$node->get($field_name)->setValue($allValues);
            
					}


				}
			}

			if (isset($values["metatag"]) && trim($values["metatag"]) != '') {
				$parts = explode('<SEPERATOR>', $values["metatag"]);

				$metaValues = array();

				// Iterate through each part
				foreach ($parts as $part) {
					// Split each part into key and value using colon
					$keyValue = explode(":", $part, 2);

					if (count($keyValue) === 2) {
						$key = trim($keyValue[0]);
						$value = trim($keyValue[1]);
						if ($key != '' && in_array($key, ["title", "description", "keywords"])) {
							$metaValues[$key] = $value;
						}
					}
				}
				if (!empty($metaValues)) {
					$node->set("field_meta_tags", serialize($metaValues));
				}
			}

			$node->save();

			if (isset($values["path"]) && trim($values["path"]) != '') {
				try {
					$path_alias = PathAlias::create([
						'path' => '/node/' . $node->id(),
						'alias' => $values["path"],
					]);
					$path_alias->save();
				} catch (Exception $e) {
				}

			}

			//die("Testing");

		}
		$context['results'][] = $index;
		$context['message'] = t('Processed chunk @index', ['@index' => $index]);
	}

	public function importFinished($success, $results, $operations)
	{
		$msgObj = $this->msgObj;
		if ($success) {
			$msgObj->addMessage(t("Data imported successfully !!"));
		} else {
			$msgObj->addError(t("Data import has some issue!!"));

		}
	}

	public function getNodeIds($allValues, $bundles)
	{
		$node_ids = [];

		// Split the title values into an array.
		$titles = explode('<SEPERATOR>', $allValues);

		// Query nodes based on title and content type.
		$query = $this->entityTypeManager->getStorage('node')->getQuery();
		$query->condition('type', $bundles, 'IN');
		$query->condition('title', $titles, 'IN');
		$query->accessCheck(FALSE);

		$node_ids = $query->execute();

		return $node_ids;
	}
	public function getTermIds($allValues, $vocabularies)
	{
		$term_ids = [];

		// Split the title values into an array.
		$titles = explode('<SEPERATOR>', $allValues);

		// Query terms based on title and vocabulary.
		$query = $this->entityTypeManager->getStorage('taxonomy_term')->getQuery();
		$query->condition('vid', $vocabularies, 'IN');
		$query->condition('name', $titles, 'IN');
		$query->accessCheck(FALSE);
		$term_ids = $query->execute();

		return $term_ids;
	}

	public function getUserIds($allValues)
	{
		$user_ids = [];

		// Split the username values into an array.
		$usernames = explode('<SEPERATOR>', $allValues);

		// Query users based on username and roles.
		$query = $this->entityTypeManager->getStorage('user')->getQuery();
		$query->condition('name', $usernames, 'IN');
		$query->accessCheck(FALSE); // Bypass access checks.
		$user_ids = $query->execute();

		return $user_ids;
	}

	protected function processCSVFile($file_path)
	{
		$csv_data = [];

		$csvFile = fopen($file_path, 'r');
		if ($csvFile) {
			$keys = fgetcsv($csvFile);
			while ($row = fgetcsv($csvFile)) {
				$rowData = array_combine($keys, $row);
				$csv_data[] = $rowData;
			}
			fclose($csvFile);
		}

		return $csv_data;

	}
	public function getDirectoryName($directory_pattern)
	{
		$contains_tokens = $this->containsTokens($directory_pattern);

		if ($contains_tokens) {
			$token_service = \Drupal::token();

			// Replace tokens in the directory pattern.
			$directory = $token_service->replace($directory_pattern, [], ['clear' => TRUE]);
			/*
							  echo "directory: ";
							  die(dump($directory));
							  
							  $bubbleable_metadata = new BubbleableMetadata();
							  $bubbleable_metadata->addCacheContexts(['url.query_args']);
							  $bubbleable_metadata->applyTo([$directory]);
							  */
		} else {
			$directory = $directory_pattern;
		}

		return $directory;
	}

	public function containsTokens($string)
	{
		return preg_match('/\[[a-zA-Z:_]+\]/', $string) === 1;
	}

	public function process_media_string($input_string)
	{
		$media_data = [];
		$media_ids = [];

		$media_entries = explode('<SEPERATOR>', $input_string);

		foreach ($media_entries as $entry) {
			list($type, $url) = explode('::::', $entry);
			$media_data[$type] = $url;
		}

		foreach ($media_data as $type => $url) {
			// Check if the URL is already associated with a media entity.

			//$media = $this->get_media_by_url($url);
			$media = NULL;

			if (!$media) {
				// If not, create a new media entity and associate it with the URL.
				$media = $this->create_media_from_url($url, $type);
			}

			if ($media) {
				$media_ids[] = $media->id();
			}
		}

		return $media_ids;
	}

	public function get_media_by_url($url)
	{
		$query = \Drupal::entityQuery('media')
			->condition('field_media_file.uri', $url)
			->range(0, 1);

		$query->accessCheck(FALSE);
		$media_ids = $query->execute();
		//echo "Media Ids: ";
		//die(dump($media_ids));

		if (!empty($media_ids)) {
			$media_id = reset($media_ids);
			return Media::load($media_id);
		}

		return NULL;
	}

	public function create_media_from_url($url, $type)
	{
		$file_url = $url;
		$urlParts = parse_url($url);

		// Get the path from the URL
		$path = $urlParts['path'];

		// Extract the image filename
		$filename = basename($path);

		//echo "File Name: ".$filename."<hr/>"; 
		//$filename = date("YmdHis");
		if ($type != "remote_video") {
			// Create a new file entity from the remote URL.
			$base_url = \Drupal::request()->getSchemeAndHttpHost();
			//if (strpos($file_url, 'http://') !== 0 && strpos($file_url, 'https://') !== 0) {
			//$file_url = $base_url.$file_url;
			//}
			$drupalRoot = \Drupal::root() . "/";
			$file_data = file_get_contents($file_url);

			//dump($file_data);
			//echo $file_url."<hr/>";

			file_put_contents($drupalRoot . "sites/default/files/" . $filename, $file_data);

			$destination = 'public://' . $filename;

			//$this->filesystem->move($drupalRoot."sites/default/files/".$filename, $destination, FileSystemInterface::EXISTS_REPLACE);

			// Create a file entity from the downloaded image
			$file = \Drupal::entityTypeManager()->getStorage('file')->create([
				'uri' => $destination,
			]);

			$file->save();

			//echo $file->id()."<hr/>";
			if ($type == "image") {
				$targetField = "field_media_image";
			} else if ($type == "document") {
				$targetField = "field_media_document";
			} else if ($type == "video") {
				$targetField = "field_media_video_file";
			} else if ($type == "audio") {
				$targetField = "field_media_audio_file";
			} else if ($type == "remote_video") {
				$targetField = "field_media_document";
			}

			$media = Media::create([
				'bundle' => $type,
				'name' => $filename,
				$targetField => [
					'target_id' => $file->id(),
				],
			]);

		} else {
			$filename = date("YmdHis");
			$media = Media::create([
				'bundle' => $type,
				'name' => $filename,
				'field_media_oembed_video' => $url, // Adjust the field name accordingly.
			]);
		}
		$media->save();

		return $media;
	}

}
