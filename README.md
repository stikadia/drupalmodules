Created Custom Twig Extensions 
1. Twig Extension: **array_count**
- array_count_values() returns an array using the values of array (which must be ints or strings) as keys and their frequency in array as values.
- Returns an associative array of values from array as keys and their count as value.
- {{ array|array_count }}
- Output for Array: array(1, "hello", 1, "world", "hello")
- Array([1] => 2,[hello] => 2,[world] => 1)

2. Twig Extension: media_url
- Upload Image, Video or Any file in media section. Now when you upload any asset in media it generate media id.
- Now you this id, you can attach that asset in your custom HTML/Layout pages.
- How to use it? If the media id of image is "3". Then
   **<img src="{{ 3|media_url }}" />** this will return full url of image/media like "http://YOURWEBSITE/sites/default/files/imagename.jpg"
  Same way you can get media url for PDF/Text/Video files.
