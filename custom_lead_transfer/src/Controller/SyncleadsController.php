<?php

namespace Drupal\custom_lead_transfer\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Response;

class SyncleadsController extends ControllerBase {
  
  public function syncLeads() {
    // Logic to retrieve webform submissions
    // Example:
    $webform_id = "museum_visit";
    $webform_submissions = \Drupal::entityTypeManager()->getStorage('webform_submission')->loadByProperties(['webform_id' => $webform_id]);
    
    \Drupal::database()->truncate('museum_visit')->execute();
    // Logic to process and insert submissions into the custom table
    foreach ($webform_submissions as $submission) {
      // Example: Get submission data
      $data = $submission->getData();
      $sid = $submission->id();
      // die(dump($data));
      $formatteddate = date('Y-m-d', strtotime($data['date_of_visit']));
      // die(dump($formattedtimeslot));
      // Example: Insert data into custom table
      if(isset($data['name'],$data['email_id']) && !empty($data['email_id']) && $data['name'])
      {
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
    
    // Return a response (optional)
    return new Response('Webform leads synced successfully.');
  }
}