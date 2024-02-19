# Custom Twig Extension Module in Drupal

## Twig Extension: array_count

- `array_count_values()` returns an array using the values of array (which must be ints or strings) as keys and their frequency in array as values.
- Returns an associative array of values from array as keys and their count as value.
- Usage: `{{ array|array_count }}`
- Output for Array: `array(1, "hello", 1, "world", "hello")`
- Result: `Array([1] => 2, [hello] => 2, [world] => 1)`

## Twig Extension: media_url

- Upload Image, Video or Any file in media section. Now when you upload any asset in media it generates a media id.
- Now you can use this id to attach that asset in your custom HTML/Layout pages.
- How to use it? If the media id of an image is "3", then ``<img src="{{ 3|media_url }}" />`` this will return the full URL of the image/media like "http://YOURWEBSITE/sites/default/files/imagename.jpg".
  Similarly, you can get media URLs for PDF/Text/Video files.

## Author

This module is authored by Sharad Tikadia.
