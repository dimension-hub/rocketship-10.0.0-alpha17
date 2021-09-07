<?php

namespace Drupal\custom_events\EventSubscriber;

use Drupal\custom_events\Event\CustomEventsPreprocessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\LegacyEventProxy;

/**
 * Dummy event subscriber.
 */
class CustomEventsSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      CustomEventsPreprocessEvent::PREPROCESS_HTML => ['preprocessHtml', 100],
      CustomEventsPreprocessEvent::PREPROCESS_PAGE => ['preprocessPage'],
    ];
  }

  /**
   * Example for DummyFrontpageEvent::PREPROCESS_HTML.
   */
  public function preprocessHtml(CustomEventsPreprocessEvent $event): void {
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::service('messenger');
    $messenger->addMessage('Event for preprocess HTML called');
  }

  /**
   * Example for DummyFrontpageEvent::PREPROCESS_HTML.
   */
  public function preprocessPage(CustomEventsPreprocessEvent $event, LegacyEventProxy $eventProxy): void {
    /** @var \Drupal\Core\Messenger\MessengerInterface $messenger */
    $messenger = \Drupal::service('messenger');
    $variables = $event->getVariables();
    $sidebars_found = 0;
    foreach ($variables['page'] as $key => $value) {
      if (preg_match("/sidebar_(.+)/", $key)) {
        $sidebars_found++;
      }
    }
    $messenger->addMessage("Found {$sidebars_found} sidebar(s) on the page");
    // Stop further execution.
    $eventProxy->stopPropagation();
  }

}
