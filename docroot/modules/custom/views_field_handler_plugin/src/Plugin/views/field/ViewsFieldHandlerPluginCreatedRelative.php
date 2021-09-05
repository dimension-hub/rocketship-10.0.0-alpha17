<?php

namespace Drupal\views_field_handler_plugin\Plugin\views\field;

use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\field\FieldPluginBase;
use Drupal\views\ResultRow;

/**
 * @ingroup views_field_handlers
 *
 * @ViewsField("views_field_handler_plugin_created_relative")
 */
class ViewsFieldHandlerPluginCreatedRelative extends FieldPluginBase {

  /**
   * {@inheritdoc}
   */
  public function query(): void {
    // We don't need to modify query for this particular example.
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions(): array {
    $options = parent::defineOptions();
    $options['relative_date_format'] = ['default' => 'd.m.Y'];
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state): void {
    $form['relative_date_format'] = [
      '#type' => 'textfield',
      '#required' => TRUE,
      '#title' => $this->t('Relative date format'),
      '#description' => $this->t('This format will be used for dates older than 7 days.'),
      '#default_value' => $this->options['relative_date_format'],
    ];
    parent::buildOptionsForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function render(ResultRow $values) {
    $created = $values->_entity;
    // Default result if conditions will fail.
    $pub_date = date($this->options['relative_date_format'], $created);
    if ($created >= strtotime('today')) {
      $pub_date = $this->t('today');
    }
    elseif ($created >= strtotime('-1 day')) {
      $pub_date = $this->t('tomorrow');
    }
    elseif ($created >= strtotime('-2 days')) {
      $pub_date = $this->t('day before yesterday');
    }
    elseif ($created >= strtotime('-3 days')) {
      $pub_date = \Drupal::translation()->formatPlural(3, '@count day ago', '@count days ago', ['@count' => 3]);
    }
    elseif ($created >= strtotime('-4 days')) {
      $pub_date = \Drupal::translation()->formatPlural(4, '@count day ago', '@count days ago', ['@count' => 4]);
    }
    elseif ($created >= strtotime('-5 days')) {
      $pub_date = \Drupal::translation()->formatPlural(5, '@count day ago', '@count days ago', ['@count' => 5]);
    }
    elseif ($created >= strtotime('-6 days')) {
      $pub_date = \Drupal::translation()->formatPlural(6, '@count day ago', '@count days ago', ['@count' => 6]);
    }
    elseif ($created >= strtotime('-7 days')) {
      $pub_date = \Drupal::translation()->formatPlural(7, '@count day ago', '@count days ago', ['@count' => 7]);
    }

    return [
      '#markup' => $pub_date,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function clickSort($order): void {
    $this->query->addOrderBy('node_field_data', 'created', $order);
  }

}
