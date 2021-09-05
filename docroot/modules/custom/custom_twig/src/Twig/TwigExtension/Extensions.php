<?php

namespace Drupal\custom_twig\Twig\TwigExtension;

use Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException;
use Drupal\Component\Plugin\Exception\PluginNotFoundException;
use Drupal\Component\Render\FormattableMarkup;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

/**
 * Custom twig extensions.
 */
class Extensions extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFunctions(): array {
    $functions = [];

    $functions[] = new TwigFunction('site_url', [
      $this,
      'siteUrl',
    ]);

    $functions[] = new TwigFunction('contact_form', [
      $this,
      'contactForm',
    ]);

    $functions[] = new TwigFunction('node_modal_link', [
      $this,
      'nodeModalLink',
    ]);

    return $functions;
  }

  /**
   * {@inheritdoc}
   */
  public function getFilters(): array {
    $filters = [];

    $filters[] = new TwigFilter('tel', [
      $this,
      'tel',
    ]);

    return $filters;
  }

  /**
   * Return the base site URL with protocol.
   */
  public function siteUrl(): string {
    return \Drupal::request()->getSchemeAndHttpHost();
  }

  /**
   * Return contact form if set otherwise default will be printed.
   */
  public function contactForm(string $contact_form_id = 'default_form') {
    try {
      $contact_message = \Drupal::entityTypeManager()
        ->getStorage('contact_message')
        ->create([
          'contact_form' => $contact_form_id,
        ]);
    }
    catch (InvalidPluginDefinitionException | PluginNotFoundException $e) {
    }
    $form = \Drupal::service('entity.form_builder')
      ->getForm($contact_message, 'default');
    $form['#title'] = $contact_message->label();
    $form['#cache']['contexts'][] = 'user.permissions';
    return $form;
  }

  /**
   * Prints link which open node in modal window.
   */
  public function nodeModalLink(int $nid, string $link_title = NULL, array $link_options = []): array {
    $link_options_defaults = [
      'attributes' => [
        'class' => ['use-ajax'],
        'data-dialog-type' => 'modal',
        'data-dialog-options' => [
          'width' => 'auto',
        ],
        'rel' => 'nofollow',
      ],
    ];

    // This array is not need to be merged.
    if (!empty($link_options['attributes']['data-dialog-options']['width'])) {
      unset($link_options_defaults['attributes']['data-dialog-options']['width']);
    }
    $link_options_merged = array_merge_recursive($link_options_defaults, $link_options);
    // Modal settings must be in json format.
    $link_options_merged['attributes']['data-dialog-options'] = Json::encode($link_options_merged['attributes']['data-dialog-options']);

    if (!$link_title) {
      $node = Node::load($nid);
      $link_title = $node->label();
    }

    return [
      '#type' => 'link',
      '#title' => $link_title,
      '#url' => Url::fromRoute('entity.node.canonical', ['node' => $nid]),
      '#options' => $link_options_merged,
      '#attached' => ['library' => ['core/drupal.dialog.ajax']],
    ];
  }

  /**
   * Return tel link.
   */
  public function tel(string $phone): FormattableMarkup {
    // Replace all spaces, brackets and dashes to valid tel phone number.
    $tel = preg_replace('/\s+|\(|\)|-/', '', $phone);
    // Workaround for issue #2484693.
    return new FormattableMarkup('<a href="tel:@tel">@phone</a>', [
      '@tel' => $tel,
      '@phone' => $phone,
    ]);
  }

}
