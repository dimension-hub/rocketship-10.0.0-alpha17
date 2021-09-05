<?php

namespace Drupal\dummy\Service;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;

/**
 * Class ServiceWithArguments.
 */
class ServiceWithArguments {

  /**
   * Node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $nodeStorage;

  /**
   * ServiceWithArguments constructor.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, FileSystemInterface $fileSystem) {
    try {
      $this->nodeStorage = $entity_type_manager->getStorage('node');
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
    }
  }

}
