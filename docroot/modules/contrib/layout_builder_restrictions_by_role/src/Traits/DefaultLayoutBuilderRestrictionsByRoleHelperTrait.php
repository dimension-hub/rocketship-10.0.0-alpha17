<?php

namespace Drupal\layout_builder_restrictions_by_role\Traits;

/**
 * Methods to help Layout Builder Restrictions By Region plugin.
 */
trait DefaultLayoutBuilderRestrictionsByRoleHelperTrait {

  /**
   * Checks if any restrictions are enabled for a given region.
   *
   * Either $static_id or $entity_view_display_id is required.
   *
   * @param string $role
   *   The id of the role.
   * @param string $category
   *   The block category name.
   * @param mixed $static_id
   *   (optional) A unique string representing a built form; optionally NULL.
   * @param mixed $entity_view_display_id
   *   (optional) The ID of the entity view display; optionally NULL.
   *
   * @return bool
   *   A boolean indicating whether or not a region has restrictions.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function roleRestrictionStatus(string $role, $static_id = NULL) {
    $settings = $this->getSettings($static_id);

    if (!isset($settings['__blocks'][$role])) {
      // No restrictions.
      return FALSE;
    }
    foreach ($settings['__blocks'][$role] as $category => $_settings) {
      if (!isset($_settings['restriction_type'])) {
        // No restrictions here.
        continue;
      }
      if ($_settings['restriction_type'] === 'all') {
        // No restrictions here.
        continue;
      }
      // White or blacklisted.
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Wrapper function for roleRestrictionStatus() that returns a string.
   *
   * Either $static_id or $entity_view_display_id is required.
   *
   * @param string $role
   *   The id of the role.
   * @param string $category
   *   The block category name.
   * @param mixed $static_id
   *   (optional) A unique string representing a built form; optionally NULL.
   * @param mixed $entity_view_display_id
   *   (optional) The ID of the entity view display; optionally NULL.
   *
   * @return string
   *   Either 'Restricted' or 'Unrestricted'.
   */
  protected function roleRestrictionsStatusString(string $role, $static_id = NULL) {
    $restriction = $this->roleRestrictionStatus($role, $static_id);
    if ($restriction == TRUE) {
      return 'Restricted';
    }
    elseif ($restriction == FALSE) {
      return 'Unrestricted';
    }
  }

  /**
   * @param string $role
   * @param string $layout_plugin_id
   * @param null $static_id
   * @param null $entity_view_display_id
   *
   * @return string
   */
  protected function layoutRoleRestrictionStatusString(string $role, string $layout_plugin_id, $static_id = NULL) {
    $restriction = $this->layoutRoleRestrictionStatus($role, $layout_plugin_id, $static_id);
    $addendum = '';

    // TODO: see if we can add this extra bit of info. Would require more ajax
    // stuff though to keep it in sync, so maybe later.
    //    $addendum = ' (Enabled)';
    //    // Check if this layout is checked/enabled.
    //    $settings = $this->getSettings($entity_view_display_id, $static_id);
    //    if (isset($settings['layout_restriction']) && $settings['layout_restriction'] == 'restricted') {
    //      // Check to see if this layout is allowed for this role.
    //      if (empty($settings['allowed_layouts'][$layout_plugin_id][$role])) {
    //        // Not allowed.
    //        $addendum = ' (Disabled)';
    //      }
    //    }

    if ($restriction == TRUE) {
      return 'Restricted' . $addendum;
    }
    elseif ($restriction == FALSE) {
      return 'Unrestricted' . $addendum;
    }
  }

  /**
   * @param string $role
   * @param string $layout_plugin_id
   * @param null $static_id
   * @param null $entity_view_display_id
   *
   * @return bool
   */
  protected function layoutRoleRestrictionStatus(string $role, string $layout_plugin_id, $static_id = NULL) {
    $settings = $this->getSettings($static_id);

    if (!isset($settings['__layouts'][$layout_plugin_id][$role])) {
      // No restrictions.
      return FALSE;
    }
    foreach ($settings['__layouts'][$layout_plugin_id][$role] as $category => $_settings) {
      if (!isset($_settings['restriction_type'])) {
        // No restrictions here.
        continue;
      }
      if ($_settings['restriction_type'] === 'all') {
        // No restrictions here.
        continue;
      }
      if (!empty($_settings['restrictions'])) {
        // Got a restriction.
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Gets the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManager
   *   Manages entity type plugin definitions.
   */
  protected function entityTypeManager() {
    return $this->entityTypeManager ?? \Drupal::service('entity_type.manager');
  }

  /**
   * Gets the private tempStore.
   *
   * @return \Drupal\Core\TempStore\PrivateTempStoreFactory
   *   Creates a private temporary storage for a collection.
   */
  protected function privateTempStoreFactory() {
    return $this->privateTempStoreFactory ?? \Drupal::service('tempstore.private');
  }

  /**
   * @param null $static_id
   *
   * @return array
   */
  protected function getSettings($static_id = NULL) {
    $third_party_settings = $this->config('layout_builder_restrictions_by_role.settings')->getRawData();
    $temporary_third_party_settings = [];
    if ($static_id) {
      $tempstore = $this->privateTempStoreFactory();
      $store = $tempstore->get('layout_builder_restrictions_by_role');
      $temporary_third_party_settings = $store->get($static_id) ?? [];
    }

    return $this->mergeTemporaryDataIntoThirdPartySettings($third_party_settings, $temporary_third_party_settings);
  }

  /**
   * @param array $settings
   * @param array $newSettings
   *
   * @return array
   */
  protected function mergeTemporaryDataIntoThirdPartySettings(array $settings, array $newSettings) {
    foreach ($newSettings as $key => $value) {
      switch ($key) {
        case '__blocks':
          foreach ($value as $_key => $_value) {
            // These keys are roles. newSettings beats Settings.
            $settings[$key][$_key] = $_value;
          }
          break;
        case '__layouts':
          foreach ($value as $_key => $_value) {
            // These keys are layouts.
            foreach ($_value as $__key => $__value) {
              // These keys are roles. newSettings beats Settings.
              $settings[$key][$_key][$__key] = $__value;
            }
          }
          break;
        case 'layout_restriction':
          $settings[$key] = $value;
          break;
        case 'allowed_layouts':
          foreach ($value as $_key => $_value) {
            // These keys are layouts. newSettings beats Settings.
            $settings[$key][$_key] = $_value;
          }
          break;
      }
    }
    return $settings;
  }

}
