<?php

namespace Drupal\layout_builder_lock\Access;

use Drupal\Core\Access\AccessResultAllowed;
use Drupal\Core\Access\AccessResultForbidden;
use Drupal\Core\Cache\RefinableCacheableDependencyInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\layout_builder\DefaultsSectionStorageInterface;
use Drupal\layout_builder\Plugin\SectionStorage\OverridesSectionStorage;
use Drupal\layout_builder\SectionStorageInterface;
use Drupal\layout_builder_lock\LayoutBuilderLock;
use Symfony\Component\Routing\Route;

/**
 * Layout Builder Lock Access Check.
 *
 * @ingroup layout_builder_access
 */
class LayoutBuilderLockAccessCheck implements AccessInterface {

  /**
   * The route match.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $routeMatch;

  /**
   * Construct a new Layout Builder Lock Access Check.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match.
   */
  public function __construct(RouteMatchInterface $route_match) {
    $this->routeMatch = $route_match;
  }

  /**
   * Checks routing access to the layout using lock settings.
   *
   * @param \Drupal\layout_builder\SectionStorageInterface $section_storage
   *   The section storage.
   * @param \Drupal\Core\Session\AccountInterface $account
   *   The current user.
   * @param \Symfony\Component\Routing\Route $route
   *   The route to check against.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function access(SectionStorageInterface $section_storage, AccountInterface $account, Route $route) {
    $operation = $route->getRequirement('_layout_builder_lock_access');

    // Use default access check in case this is a default section or if the user
    // has permission to manage lock settings.
    if ($section_storage instanceof DefaultsSectionStorageInterface && $account->hasPermission('manage lock settings on sections')) {
      return new AccessResultAllowed();
    }

    // Allow access on overrides with the
    // 'bypass lock settings on layout overrides' permission.
    if ($section_storage instanceof OverridesSectionStorage && $account->hasPermission('bypass lock settings on layout overrides')) {
      return new AccessResultAllowed();
    }

    // Get delta from route params.
    $delta = $this->routeMatch->getRawParameter('delta');
    if (!isset($delta)) {
      // This shouldn't happen normally, but you never know.
      return new AccessResultAllowed();
    }

    // Default settings.
    $check_before_and_after = FALSE;
    $lock_settings = $lock_settings_before = $lock_settings_after = [];

    // The add section operation is a bit more complex when delta is not 0.
    // In case of a higher number, we need to get any lock settings from the
    // section before and after.
    if ($operation == 'section_add' && $delta > 0) {

      $check_before_and_after = TRUE;

      // Ignore in case the next section doesn't exist at all.
      try {
        $lock_settings_before = array_filter($section_storage
          ->getSection($delta - 1)
          ->getThirdPartySetting('layout_builder_lock', 'lock', LayoutBuilderLock::NO_LOCK));
      }
      catch (\OutOfBoundsException $ignored) {}

      // Ignore in case the next section doesn't exist at all.
      try {
        $lock_settings_after = array_filter($section_storage
          ->getSection($delta + 1)
          ->getThirdPartySetting('layout_builder_lock', 'lock', LayoutBuilderLock::NO_LOCK));
      }
      catch (\OutOfBoundsException $ignored) {}
    }
    elseif (
      $operation == 'section_edit' &&
      $this->routeMatch->getRawParameter('plugin_id')
    ) {

      // When adding a new section, the section configuration form needs to be
      // displayed and the operation for that will be section_edit. Now because
      // we are still adding a new section, the access for that is already
      // determined in the previous step through the section_add operation and
      // the deltas will not be updated yet, meaning if we get the lock settings
      // based on the current delta the below else statement will return the
      // wrong section and could result in a forbidden access, depending on the
      // lock permissions of that section.
      return new AccessResultAllowed();
    }
    else {

      try {
        $lock_settings = array_filter($section_storage
          ->getSection($delta)
          ->getThirdPartySetting('layout_builder_lock', 'lock', LayoutBuilderLock::NO_LOCK));
      }
      catch (\OutOfBoundsException $ignored) {}

      // Use default access in case the settings are empty.
      if (empty($lock_settings)) {
        return new AccessResultAllowed();
      }
    }

    // Get default components.
    $default_components = [];
    try {
      if ($section_storage instanceof OverridesSectionStorage) {
        $default_components = $section_storage->getDefaultSectionStorage()->getSection($delta)->getComponents();
      }
      else {
        $default_components = $section_storage->getSection($delta)->getComponents();
      }
    }
    catch (\OutOfBoundsException $ignored) {}

    // Section storage access.
    $access = $section_storage->access($operation, $account, TRUE);

    switch ($operation) {
      case 'block_add':
        if (isset($lock_settings[LayoutBuilderLock::LOCKED_BLOCK_ADD])) {
          return new AccessResultForbidden();
        }
        break;

      case 'block_config':
        $uuid = $this->routeMatch->getRawParameter('uuid');
        if (isset($lock_settings[LayoutBuilderLock::LOCKED_BLOCK_UPDATE]) && isset($default_components[$uuid])) {
          $access = new AccessResultForbidden();
        }
        break;

      case 'block_remove':
        $uuid = $this->routeMatch->getRawParameter('uuid');
        if (isset($lock_settings[LayoutBuilderLock::LOCKED_BLOCK_DELETE]) && isset($default_components[$uuid])) {
          $access = new AccessResultForbidden();
        }
        break;

      case 'block_reorder':
        $uuid = $this->routeMatch->getRawParameter('uuid');
        if (isset($lock_settings[LayoutBuilderLock::LOCKED_BLOCK_MOVE]) && isset($default_components[$uuid])) {
          $access = new AccessResultForbidden();
        }

        if (isset($lock_settings[LayoutBuilderLock::LOCKED_SECTION_BLOCK_MOVE])) {
          try {
            if (count($section_storage->getSection($delta)->getComponents()) == count($default_components)) {
              $access = new AccessResultForbidden();
            }
          }
          catch (\OutOfBoundsException $ignored) {}
        }

        break;

      case 'section_add':
        if ($check_before_and_after) {
          if (isset($lock_settings_before[LayoutBuilderLock::LOCKED_SECTION_AFTER]) || isset($lock_settings_after[LayoutBuilderLock::LOCKED_SECTION_BEFORE]) || isset($lock_settings_after[LayoutBuilderLock::LOCKED_SECTION_BEFORE])) {
            $access = new AccessResultForbidden();
          }
        }
        else {
          // This only needs the before check since the delta is 0.
          if (isset($lock_settings[LayoutBuilderLock::LOCKED_SECTION_BEFORE])) {
            $access = new AccessResultForbidden();
          }
        }
        break;

      case 'section_edit':
        if (isset($lock_settings[LayoutBuilderLock::LOCKED_SECTION_CONFIGURE]) && !$account->hasPermission('manage lock settings on overrides')) {
          $access = new AccessResultForbidden();
        }
        break;

      case 'section_remove':
        // There are settings, so removing is forbidden.
        $access = new AccessResultForbidden();
        break;
    }

    if ($access instanceof RefinableCacheableDependencyInterface) {
      $access->addCacheableDependency($section_storage);
    }

    return $access;
  }

}
