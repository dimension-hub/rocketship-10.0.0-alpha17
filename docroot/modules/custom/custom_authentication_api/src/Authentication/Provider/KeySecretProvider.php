<?php

namespace Drupal\custom_authentication_api\Authentication\Provider;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class KeySecretProvider.
 */
class KeySecretProvider implements AuthenticationProviderInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager) {
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function applies(Request $request): bool {
    return $request->query->has('key');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    $key = $request->query->get('key');
    try {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->loadByProperties([
          'custom_authentication_api_key' => $key,
        ]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
    }

    if (!empty($user)) {
      return reset($user);
    }
    return NULL;
  }

}
