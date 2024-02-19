<?php
namespace Drupal\custom_api\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\media\Entity\Media;
use Drupal\custom_api\Helper;

class CustomApiController extends ControllerBase
{

  protected $entityTypeManager;
  protected $urlGenerator;
  protected $file_url_generator;

  public function __construct(EntityTypeManagerInterface $entityTypeManager, UrlGeneratorInterface $urlGenerator)
  {
    $this->entityTypeManager = $entityTypeManager;
    $this->urlGenerator = $urlGenerator;
    $this->file_url_generator = \Drupal::service('file_url_generator');
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('url_generator')
    );
  }

  public function listData(Request $request, $content_type)
  {
    try {
      
      // Get the token from the query parameter.
      $token = $request->request->get('token');
      $uid = $request->request->get('uid');
      
      // Validate the token.
      if (!Helper::validateToken($token,$uid)) {
      // Token is invalid; return an error response.
        return new JsonResponse(['error' => 'Invalid token'], 403);
      }

      $page = $request->request->get('page', 1);
      $limit = $request->request->get('limit', 10);
      $sort = $request->request->get('sort', 'created');
      $direction = $request->request->get('direction', 'asc');
      $fieldsval = $request->request->get('fields');

      $fields = [];
      if(!empty($fieldsval) && !in_array(strtolower($fieldsval), ['*', 'all']))
      {
        $fields = explode(",",$fieldsval);
      }
      
      $whereConditions = isset($_POST['where'])?$_POST['where']:'';
      
      
      // Get the entity storage for the specified content type.
      $storage = $this->entityTypeManager->getStorage('node');

      // Query the database to get the entities of the specified content type.
      $query = $storage->getQuery()
        ->condition('type', $content_type)
        ->condition('status', 1)
        ->pager($limit, $page)
        ->sort($sort, $direction);
      
      foreach ($whereConditions as $wherekey => $whereval) {
          $query->condition($wherekey, $whereval);
      }

      $query->accessCheck(FALSE);
      $entity_ids = $query->execute();

      // Load the entities and extract the data.
      $entities = $storage->loadMultiple($entity_ids);
      $data = [];

      $skipArr = ['uuid', 'vid', 'revision_timestamp', 'revision_uid', 'revision_log', 'uid', 'created', 'changed', 'promote', 'sticky', 'default_langcode', 'revision_default', 'revision_translation_affected', 'menu_link', 'content_translation_source', 'content_translation_outdated', 'comment'];

      foreach ($entities as $entity) {
        // Customize this section to extract the specific fields you need.
        // Example: $data[] = $entity->get('field_name')->value;
        $content = $entity->toArray();


        foreach ($content as $field_name => &$field) {
          if ($entity->hasField($field_name)) {
            
            if (in_array($field_name, $skipArr) || (!empty($fields) && !in_array($field_name, $fields))) {
                unset($content[$field_name]);
                continue;
            }

            $field_definition = $entity->getFieldDefinition($field_name);
            $field_type = $field_definition->getType();
            $settings = $field_definition->getSettings();
            $target_entity_type = $settings['target_type'];
            $values = $entity->{$field_name}->getValue();


            if ($field_type == "image" || $field_type == 'file') {
              foreach ($values as $key => $value) {
                $file = \Drupal\file\Entity\File::load($value['target_id']);

                if ($file) {
                  $url = $this->file_url_generator->generateAbsoluteString($file->getFileUri());

                  $content[$field_name][$key]["url"] = $url;
                }
              }
            } else if ($field_type == "entity_reference") {
              $tags = $entity->get($field_name)->referencedEntities();
              if ($target_entity_type == "media") {
                foreach ($tags as $key => $media_item) {
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
                    $content[$field_name][$key]["url"] = $file_url;
                  }
                  
                }
              }
              else if ($target_entity_type == "taxonomy_term") {
                // Handle taxonomy field.
                  $tags = $entity->get($field_name)->referencedEntities();
                  $taxonomy_data = [];
                  foreach ($tags as $taxonomy_item) {
                      $taxonomy_data[] = [
                          'tid' => $taxonomy_item->id(),
                          'name' => $taxonomy_item->getName(),
                          'url' => $this->urlGenerator->generate('entity.taxonomy_term.canonical', ['taxonomy_term' => $taxonomy_item->id()]),
                      ];
                  }
                  $content[$field_name] = $taxonomy_data;
              }
            }
          }

        }
        $data[] = $content;
      }

      $count_query = $storage->getQuery()
        ->condition('type', $content_type)
        ->condition('status', 1); // Published nodes only

      $count_query->accessCheck(FALSE);
      $total_rows = $count_query->count()->execute();

      $next_page = NULL;
      $prev_page = NULL;

      if ($total_rows > $limit) {
        $prev_page = ($page > 1) ? $this->urlGenerator->generate('custom_api.list_data', ['content_type' => $content_type, 'page' => $page - 1, 'sort' => $sort, 'direction' => $direction]) : NULL;
        $next_page = ($total_rows > ($page * $limit)) ? $this->urlGenerator->generate('custom_api.list_data', ['content_type' => $content_type, 'page' => $page + 1, 'sort' => $sort, 'direction' => $direction]) : NULL;
      }

      $total_pages = ceil($total_rows / $limit);

      return new JsonResponse([
        'total_rows' => $total_rows,
        'nextPage' => $next_page,
        'total_pages' => $total_pages,
        'prevPage' => $prev_page,
        'data' => $data,
      ]);
    } catch (\Exception $e) {
      // Handle exceptions and return an error response in JSON format.
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  
}