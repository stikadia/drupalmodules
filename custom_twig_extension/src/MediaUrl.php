<?php

namespace Drupal\custom_twig_extension;

use Drupal\file\Entity\File;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Drupal\media\Entity\Media;
use Drupal\Core\Url;
use Drupal\image\Entity\ImageStyle;

class MediaUrl extends AbstractExtension {
    
    /**
     * {@inheritdoc}
     */
    public function getFilters() {
        return [
            new TwigFilter('media_url', [$this, 'getMediaUrl']),
        ];
    }

    /**
   * Returns the media URL based on the provided media ID.
   *
   * @param int $mediaId
   *   The media ID.
   *
   * @return string|null
   *   The media URL or NULL if the media doesn't exist.
   */
  public function getMediaUrl($mediaId) {
    $media = Media::load($mediaId);

    //dump($media); die();
    if ($media) {
        $mediaType = $media->bundle();
         
        $fileUrl = '';
        if($mediaType == 'document')
        {
            $fileUrl = $media->field_media_document->entity->createFileUrl();
        }
        else if($mediaType == 'video')
        {
            $fileUrl = $media->field_media_video_file->entity->createFileUrl();
        }
        else if($mediaType == "image")
        {
            $fileUrl = $media->field_media_image->entity->getFileUri();
            // Get the image style object by its name.
            $image_style = ImageStyle::load('customwebp');

            // Check if the image style exists and the media file URL is valid.
            if ($image_style && $fileUrl) {
                // Apply the image style to the media URL.
                $fileUrl = ImageStyle::load('customwebp')->buildUrl($fileUrl);
                // Now $styled_url contains the URL of the image with the applied style.
                // You can use this URL in your code to display the styled image.
            }
            else{
                $fileUrl = $media->field_media_image->entity->createFileUrl();
            }

        }

        return $fileUrl;
    }
    return NULL;
  }

}
