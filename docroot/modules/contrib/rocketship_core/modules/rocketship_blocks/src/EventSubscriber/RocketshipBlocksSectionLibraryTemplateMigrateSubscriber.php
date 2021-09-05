<?php

namespace Drupal\rocketship_blocks\EventSubscriber;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\migrate\Event\MigrateEvents;
use Drupal\migrate\Event\MigratePostRowSaveEvent;
use Drupal\node\Entity\Node;
use Drupal\node\NodeInterface;
use Drupal\section_library\Entity\SectionLibraryTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Class RocketshipBlocksSectionLibraryTemplateMigrateSubscriber
 *
 * @package Drupal\rocketship_blocks\EventSubscriber
 */
class RocketshipBlocksSectionLibraryTemplateMigrateSubscriber implements EventSubscriberInterface {

  /**
   * Var.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * RocketshipCookiePolicySubscriber constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->configFactory = $config_factory;
  }

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    $events[MigrateEvents::POST_ROW_SAVE] = ['onMigratePostRowSaveEvent'];

    return $events;
  }

  /**
   * Callback for the event.
   *
   * This does assume there's only one node being migrated. If there are
   * multiple then the last one will stick, as this is executed for every row.
   *
   * @param \Drupal\migrate\Event\MigratePostRowSaveEvent $event
   *   The event.
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  public function onMigratePostRowSaveEvent(MigratePostRowSaveEvent $event) {
    $migration = $event->getMigration();
    if ($migration->id() === 'rs_blocks_templates') {
      $IDs = $event->getDestinationIdValues();
      $ID = reset($IDs);
      /** @var \Drupal\section_library\Entity\SectionLibraryTemplate $template */
      $template = SectionLibraryTemplate::load($ID);
      // set the correct revision IDs? in configuration?
    }
  }

}
