# Taxonomy Migration Module

This module facilitates exporting taxonomy terms from Drupal as CSV files and provides a mechanism for importing CSV data into specific taxonomy vaoabulary within a Drupal site.

## Installation

1. Download the module and place it in the `modules/custom` directory of your Drupal installation.
2. Enable the module via the Drupal administration interface or using Drush:

   ```bash
   drush en taxonomy_migration_ia
   
## Export Taxonomy Term Data

To export content as a CSV file, follow these steps:

1. Navigate to /admin/structure/content-migration-ia.
2. Select the desired vocabulary from the dropdown menu.
3. Click the "Export" button.
4. The exported CSV file will be downloaded automatically.

## Import Taxonomy Term Data

To import taxonomy term as a CSV file, follow these steps:

1. Ensure your CSV file adheres to the expected format.
2. Navigate to /admin/structure/content-migration-ia/import.
3. Upload the CSV file using the provided form.
4. Click the "Import" button to initiate the content import process.

## Author

This module is authored by Sharad Tikadia.
