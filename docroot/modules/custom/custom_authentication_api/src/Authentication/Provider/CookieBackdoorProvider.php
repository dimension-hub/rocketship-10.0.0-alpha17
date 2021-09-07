<?php

namespace Drupal\custom_authentication_api\Authentication\Provider;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Core\Authentication\AuthenticationProviderInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class CookieBackdoorProvider.
 */
class CookieBackdoorProvider implements AuthenticationProviderInterface {

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
   * Checks whether suitable authentication credentials are on the request.
   */
  public function applies(Request $request): bool {
    return $request->cookies->has('backdoor_uid');
  }

  /**
   * {@inheritdoc}
   */
  public function authenticate(Request $request) {
    try {
      $user = $this->entityTypeManager
        ->getStorage('user')
        ->load($request->cookies->get('backdoor_uid'));
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
    }

    if ($user) {
      \Drupal::service('session_manager')->regenerate();
      return $user;
    }
    return NULL;
  }

}
