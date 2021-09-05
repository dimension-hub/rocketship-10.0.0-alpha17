<?php

namespace Drupal\layout_builder\ConfigTranslation;

use Drupal\config_translation\ConfigEntityMapper;
use Drupal\config_translation\Event\ConfigMapperPopulateEvent;
use Drupal\config_translation\Event\ConfigTranslationEvents;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\layout_builder\Form\DefaultsTranslationForm;
use Drupal\layout_builder\LayoutEntityHelperTrait;
use Symfony\Component\Routing\Route;

/**
 * Provides a configuration mapper for entity displays Layout Builder settings.
 */
class LayoutEntityDisplayMapper extends ConfigEntityMapper {

  use LayoutEntityHelperTrait;

  /**
   * Loaded entity instance to help produce the translation interface.
   *
   * @var \Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay
   */
  protected $entity;

  /**
   * {@inheritdoc}
   */
  public function populateFromRouteMatch(RouteMatchInterface $route_match) {
    $view_mode = $route_match->getParameter('view_mode_name');
    $definition = $this->getPluginDefinition();

    $target_entity_type_id = $definition['target_entity_type'];
    $target_entity_type = $this->entityTypeManager->getDefinition($target_entity_type_id);
    $bundle_entity_type = $target_entity_type->getBundleEntityType();
    $bundle = $route_match->getParameter($bundle_entity_type ?: 'bundle') ?: $target_entity_type_id;


    $entity = $this->entityTypeManager->getStorage('entity_view_display')->load($target_entity_type_id . '.' . $bundle . '.' . $view_mode);
    $this->setEntity($entity);

    $this->langcode = $route_match->getParameter('langcode');

    $event = new ConfigMapperPopulateEvent($this, $route_match);
    $this->eventDispatcher->dispatch(ConfigTranslationEvents::POPULATE_MAPPER, $event);
  }

  /**
   * {@inheritdoc}
   */
  public function hasTranslatable() {
    $section_storage = $this->getSectionStorageForEntity($this->entity);
    foreach ($section_storage->getSections() as $section) {
      foreach ($section->getComponents() as $component) {
        // @todo Determine if component has translatable schema.
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function getBaseRouteParameters() {
    $target_entity_type = $this->entityTypeManager->getDefinition($this->entity->getTargetEntityTypeId());
    if ($bundle_type = $target_entity_type->getBundleEntityType()) {
      $parameters[$bundle_type] = $this->entity->getTargetBundle();
    }

    $parameters['view_mode_name'] = $this->entity->getMode();
    return $parameters;
  }

  /**
   * {@inheritdoc}
   */
  public function getAddRoute() {
    $route = parent::getAddRoute();
    $this->modifyAddEditRoutes($route);
    return $route;
  }

  /**
   * {@inheritdoc}
   */
  public function getEditRoute() {
    $route = parent::getEditRoute();
    $this->modifyAddEditRoutes($route);
    return $route;
  }

  /**
   * Modifies to add and edit routes to use DefaultsTranslationForm.
   *
   * @param \Symfony\Component\Routing\Route $route
   *   The route to modify.
   *
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  protected function modifyAddEditRoutes(Route $route) {
    $definition = $this->getPluginDefinition();
    $target_entity_type = $this->entityTypeManager->getDefinition($definition['target_entity_type']);
    if ($bundle_type = $target_entity_type->getBundleEntityType()) {
      $route->setDefault('bundle_key', $bundle_type);
    }
    else {
      $route->setDefault('bundle', $definition['target_entity_type']);
    }

    $route->setDefault('entity_type_id', $definition['target_entity_type']);
    $route->setDefault('_form', DefaultsTranslationForm::class);
    $route->setDefault('section_storage_type', 'defaults');
    $route->setDefault('section_storage', '');
    $route->setOption('_layout_builder', TRUE);
    $route->setOption('_admin_route', FALSE);
    $route->setOption('parameters', [
      'section_storage' => ['layout_builder_tempstore' => TRUE],
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getTitle() {
    return parent::getTitle() . ': ' . $this->entity->getMode();
  }

}
