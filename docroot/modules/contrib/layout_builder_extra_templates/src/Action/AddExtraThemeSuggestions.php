<?php

namespace Drupal\layout_builder_extra_templates\Action;

/**
 * Add extra theme template suggestions.
 */
class AddExtraThemeSuggestions {

  /**
   * Add extra theme template suggestions.
   *
   * @see layout_builder_extra_templates_theme_suggestions_block_alter()
   */
  public static function add(array &$variables, array &$suggestions) {
    if (!self::isCorrectPlugin($variables)) {
      return;
    }

    $themeName = self::getActiveTheme();
    $blockType = self::getBlockBundle($variables['elements']);

    self::addBasicSuggestion($suggestions, $themeName, $blockType);
    self::addSuggestionWithPluginId($suggestions, $variables, $themeName, $blockType);
  }

  /**
   * Ensure that we're only acting on the correct plugins (i.e. blocks).
   */
  private static function isCorrectPlugin(array $variables) {
    return array_search($variables['elements']['#base_plugin_id'], ['block_content', 'inline_block']) !== FALSE;
  }

  /**
   * Get the name of the active theme.
   */
  private static function getActiveTheme() {
    return \Drupal::theme()->getActiveTheme()->getName();
  }

  /**
   * Get the bundle type for a block.
   */
  private static function getBlockBundle(array $elements) {
    if ($elements['#base_plugin_id'] == 'inline_block') {
      return $elements['#derivative_plugin_id'];
    }

    /** @var \Drupal\block_content\BlockContentInterface $blockContent */
    $blockContent = $elements['content']['#block_content'];

    return $blockContent->bundle();
  }

  /**
   * Add a new suggestion that includes the theme name.
   */
  private static function addBasicSuggestion(array &$suggestions, $themeName, $blockType) {
    array_splice($suggestions, 2, 0, "block__{$themeName}__{$blockType}");
    array_splice($suggestions, 2, 0, "block__{$blockType}");
  }

  /**
   * Add a new suggestion including the block type and the base plugin ID.
   */
  private static function addSuggestionWithPluginId(array &$suggestions, array $variables, $themeName, $blockType) {
    array_push($suggestions, vsprintf('block__%s__%s__%s', [
      $themeName,
      $variables['elements']['#base_plugin_id'],
      $blockType,
    ]));
  }

}
