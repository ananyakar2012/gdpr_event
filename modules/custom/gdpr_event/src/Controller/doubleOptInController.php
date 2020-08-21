<?php

namespace Drupal\gdpr_event\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\gdpr_event\Event\GdprOptInEvent;
use Drupal\Core\Entity;

/**
 * Class doubleOptInController.
 */
class doubleOptInController extends ControllerBase {

  /**
   * Actorlist.
   *
   * @return string
   *   GDPR Double opt in.
   */
  public function doubleOptIn() {
    $currentTime = date("Y-m-d h:i:s");
    $current_path = \Drupal::service('path.current')->getPath();
    $tokenValue = str_replace(" ","+",substr($current_path, strrpos($current_path,"/")+1));
    //fetch required data from token
    $query = \Drupal::database()->select('gdpr_log', 'gt');
    $query->addField('gt', 'email');
    $query->addField('gt', 'form_name');
    $query->addField('gt', 'page_url');
    $query->addField('gt', 'country');
    $query->condition('gt.token', $tokenValue);
    $dataResult = $query->execute()->fetchAll();

    //if mail id present and token has not expired then data will be stored in consent and log table
    if(isset($dataResult[0]->email)){
      //make connection to database
      $conn = \Drupal::database();
      //insert data in gdpr log table
      $conn->insert('gdpr_log')->fields(
        array(
          'email' => $dataResult[0]->email,
          'form_name' => $dataResult[0]->form_name,
          'page_url' => $dataResult[0]->page_url,
          'country' => $dataResult[0]->country,
          'event' => 'double opt in',
          'token' => $tokenValue,
          'event_timestamp' => $currentTime
        )
      )->execute();
      //insert data in consent table
      $conn->insert('gdpr_consent')->fields(
        array(
          'email' => $dataResult[0]->email,
          'created' => $currentTime,
          'consent_status' => 'YES',
          'updated' => $currentTime,
        )
      )->execute();

      //send mail to the user
      $mailManager = \Drupal::service('plugin.manager.mail');
      $module = 'gdpr_event';
      $key = 'webform_submit';
      $to = $dataResult[0]->email;
      $params['name'] = $dataResult[0]->email;
      $params['message'] = "Thank you for subscription";
      $langcode = \Drupal::currentUser()->getPreferredLangcode();
      $send = true;

      $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
      $message = $msgStatus = '';
      if ($result['result'] !== true) {
        $message = t('There was a problem sending your message and it was not sent.');
        $msgStatus = 'error';
      }
      else {
        $message = t('Your message has been sent.');
        $msgStatus = 'success';
      }
    }else{
      $message = t('Invalid Token');
      $msgStatus = 'error';
    }
    return drupal_set_message($message, $msgStatus);
  }
}
