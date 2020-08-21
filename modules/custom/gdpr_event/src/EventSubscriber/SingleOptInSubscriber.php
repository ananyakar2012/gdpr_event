<?php

namespace Drupal\gdpr_event\EventSubscriber;

use Drupal\Component\Utility\Random;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\gdpr_event\Event\GdprOptInEvent;

/**
 * Logs the creation of a new node.
 */
class SingleOptInSubscriber implements EventSubscriberInterface {

  /**
   * Log the creation of a new node.
   *
   * @param \Drupal\gdpr_event\Event\GdprOptInEvent $event
   */
  public function onConsentAccept(GdprOptInEvent $event) {
    $entity = $event->getEntity();
    $entity_type = $entity->getEntityTypeId();
    if ($entity_type == 'webform_submission') {
      $webform_id = $entity->getWebform()->id();
      $webformSubmissionData = $entity->getData();
      $current_path = \Drupal::service('path.current')->getPath();
      $currentTime = date("Y-m-d h:i:s");
      // Data insert in the custom GDPR Log table if consent is checked.
      if ($webformSubmissionData['consent'] == 1) {
        // Token generation.
        $token = base64_encode(Random::string());
        // Make connection to database.
        $conn = \Drupal::database();
        // Insert data in gdpr log table.
        $conn->insert('gdpr_log')->fields(
          [
            'email' => $webformSubmissionData['email'],
            'form_name' => $webform_id,
            'page_url' => $current_path,
            'country' => $webformSubmissionData['country'],
            'event' => 'single opt in',
            'token' => $token,
            'event_timestamp' => $currentTime,
          ]
        )->execute();

        // Send mail to the user.
        $mailManager = \Drupal::service('plugin.manager.mail');
        $module = 'gdpr_event';
        $key = 'webform_submit';
        $to = \Drupal::currentUser()->getEmail();
        $params['name'] = $webformSubmissionData['name'];
        $params['message'] = "Thank you for agree to the consent. Please click on the <a href='/gdpr-verification/" . $token . "'>
        link for verfication</a>.";
        $langcode = \Drupal::currentUser()->getPreferredLangcode();
        $send = TRUE;

        $result = $mailManager->mail($module, $key, $to, $langcode, $params, NULL, $send);
        if ($result['result'] !== TRUE) {
          drupal_set_message(t('There was a problem sending your message and it was not sent.'), 'error');
        }
        else {
          drupal_set_message(t('Your message has been sent.'));
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[GdprOptInEvent::GDPR_INFO_INSERT][] = ['onConsentAccept'];
    return $events;
  }

}
