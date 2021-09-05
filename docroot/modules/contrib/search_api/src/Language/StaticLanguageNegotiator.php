<?php

namespace Drupal\search_api\Language;

use Drupal\language\LanguageNegotiator;

/**
 * Provides a language negotiator that allows setting the current language.
 */
class StaticLanguageNegotiator extends LanguageNegotiator {

  /**
   * The language code to return for all types.
   *
   * @var string|null
   */
  protected $languageCode;

  /**
   * {@inheritdoc}
   */
  public function initializeType($type) {
    $language = NULL;
    $method_id = static::METHOD_ID;
    $availableLanguages = $this->languageManager->getLanguages();

    if ($this->languageCode && isset($availableLanguages[$this->languageCode])) {
      $language = $availableLanguages[$this->languageCode];
    }
    else {
      // If no other language was found use the default one.
      $language = $this->languageManager->getDefaultLanguage();
    }

    return [$method_id => $language];
  }

  /**
   * Sets the language code to return for all types.
   *
   * @param string|null $langcode
   *   The language code to set.
   *
   * @return $this
   */
  public function setLanguageCode($langcode) {
    $this->languageCode = $langcode;
    return $this;
  }

}
