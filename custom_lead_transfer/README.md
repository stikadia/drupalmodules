# Custom Lead Transfer Module

This module provides functionality to synchronize webform leads with a custom table in MySQL. It allows administrators to easily manage and maintain lead data captured through webforms.

## Route

### Sync Leads

- **Path:** `/admin/syncleads`
- **Title:** Sync Webform Leads to Custom Table
- **Permission:** Administer Site Configuration

## Controller

### SyncleadsController

```php
<?php

namespace Drupal\custom_lead_transfer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class SyncleadsController extends ControllerBase {
  
  public function syncLeads() {
    // Logic to retrieve webform submissions
    $webform_id = "museum_visit";
    $webform_submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties(['webform_id' => $webform_id]);
    
    \Drupal::database()->truncate('museum_visit')->execute();
    
    // Logic to process and insert submissions into the custom table
    foreach ($webform_submissions as $submission) {
      $data = $submission->getData();
      $sid = $submission->id();
      $formatteddate = date('Y-m-d', strtotime($data['date_of_visit']));
      
      // Insert data into custom table
      if(isset($data['name'],$data['email_id']) && !empty($data['email_id']) && $data['name']) {
        \Drupal::database()->insert('museum_visit')
          ->fields([
            'iSid' => $sid,
            'vName' => $data['name'],
            'vEmail' => $data['email_id'],
            'vPhone' => $data['mobile_number'],
            'vNoOfPeopleBooked' => $data['number_of_people_to_visit'],
            'dVisitDate' => $formatteddate,
            'eStatus' => $data['status']
            // Add more fields as needed
          ])
          ->execute();
      }
    }
    
    // Return a response
    return new Response('Webform leads synced successfully.');
  }
}
```

### Usage

1. Navigate to `/admin/syncleads` in the Drupal administration interface.
2. Ensure you have the necessary permissions to access this page (`administer site configuration`).
3. Click the "Sync Webform Leads to Custom Table" link.
4. The webform leads will be synchronized with the custom table `museum_visit`.
5. You'll receive a confirmation message indicating the successful synchronization.

## Author

This module is authored by Sharad Tikadia.



