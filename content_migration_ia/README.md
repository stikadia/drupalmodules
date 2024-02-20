# Content Migration Module

This module facilitates exporting content from Drupal as CSV files and provides a mechanism for importing CSV data into specific content types within a Drupal site.

## Installation

1. Download the module and place it in the `modules/custom` directory of your Drupal installation.
2. Enable the module via the Drupal administration interface or using Drush:

   ```bash
   drush en content_migration_ia
   
## Export Content

To export content as a CSV file, follow these steps:

1. Navigate to /admin/content-migration-ia.
2. Select the desired content type from the dropdown menu.
3. Click the "Export" button.
4. The exported CSV file will be downloaded automatically.

## Import Content

To export content as a CSV file, follow these steps:

1. Ensure your CSV file adheres to the expected format.
2. Navigate to /admin/content-migration-ia/import.
3. Upload the CSV file using the provided form.
4. Click the "Import" button to initiate the content import process.

## Author

This module is authored by Sharad Tikadia.
