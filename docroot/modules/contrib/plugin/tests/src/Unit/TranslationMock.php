<?php

namespace Drupal\Tests\plugin\Unit;

use Drupal\Component\Render\FormattableMarkup;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Provides a mock implementation of \Drupal\Core\StringTranslation\TranslationInterface.
 *
 * This is an alternative to UnitTestCase::getStringTranslationStub(), which
 * cannot be used inside usort() callbacks, for instance.
 */
class TranslationMock implements TranslationInterface {

  /**
   * {@inheritdoc}
   */
  public function translate($string, array $args = array(), array $options = array()) {
    return new TranslatableMarkup($string, $args, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function translateString(TranslatableMarkup $translatable_string) {
    return new FormattableMarkup($translatable_string->getUntranslatedString(), $translatable_string->getArguments());
  }

  /**
   * {@inheritdoc}
   */
  public function formatPlural($count, $singular, $plural, array $args = array(), array $options = array()) {
    return $count === 1 ? new FormattableMarkup($singular, $args) : new FormattableMarkup($plural, $args + ['@count' => $count]);
  }

  /**
   * {@inheritdoc}
   */
  public function formatPluralTranslated($count, $translation, array $args = array(), array $options = array()) {
    return new FormattableMarkup($translation, $args);
  }

  /**
   * {@inheritdoc}
   */
  public function getNumberOfPlurals($langcode = NULL) {
    return mt_rand();
  }

}
