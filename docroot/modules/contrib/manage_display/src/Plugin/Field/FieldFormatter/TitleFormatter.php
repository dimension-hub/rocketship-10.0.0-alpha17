<?php

namespace Drupal\manage_display\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\Plugin\Field\FieldFormatter\BasicStringFormatter;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * A field formatter for entity titles.
 *
 * @FieldFormatter(
 *   id = "title",
 *   label = @Translation("Title"),
 *   field_types = {
 *     "string"
 *   }
 * )
 */
class TitleFormatter extends BasicStringFormatter {

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode = NULL) {
    $output = [];
    $parent = $items->getParent()->getValue();
    foreach ($items as $item) {
      $text = $item->getValue()['value'];
      if ($this->getSetting('linked') && $this->canLink()) {
        // When previewing a new entity, there is no URL. It gives a better
        // preview if we still show a link, so use <front>.
        $url = $parent->isNew() ? Url::fromRoute('<front>') : $parent->toUrl();
        $text = Link::fromTextAndUrl($text, $url)->toString();
      }
      $output[] = [
        '#type' => 'html_tag',
        '#tag' => $this->getSetting('tag'),
        '#value' => $text,
      ];
    }
    return $output;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);
    $heading_options = [
      'span' => 'span',
      'div' => 'div',
    ];
    foreach (range(1, 5) as $level) {
      $heading_options['h' . $level] = 'H' . $level;
    }

    $form['tag'] = [
      '#title' => $this->t('Tag'),
      '#type' => 'select',
      '#description' => $this->t('Select the tag which will be wrapped around the title.'),
      '#options' => $heading_options,
      '#default_value' => $this->getSetting('tag'),
    ];

    $form['linked'] = [
      '#title' => $this->t('Link to the Content'),
      '#type' => 'checkbox',
      '#description' => $this->t('Wrap the title with a link to the content.'),
      '#default_value' => $this->getSetting('linked'),
      '#access' => $this->canLink(),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'tag' => 'h2',
      'linked' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $settings = $this->getSettings();
    $replacements = ['@tag' => $settings['tag']];
    if ($settings['linked'] && $this->canLink()) {
      $summary[] = $this->t('Display as @tag, linked to content', $replacements);
    }
    else {
      $summary[] = $this->t('Display as @tag', $replacements);
    }

    return $summary;
  }

  /**
   * Returns whether the entity type supports linking.
   *
   * @return bool
   *   TRUE if the entity type supports linking.
   */
  public function canLink() {
    $entity_type = \Drupal::entityTypeManager()->getDefinition($this->fieldDefinition->getTargetEntityTypeId());
    return $entity_type->hasLinkTemplate('canonical');
  }

}
