<?php

namespace Drupal\custom_cache_tags\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Url;
use Drupal\Core\Link;

/**
 * Provides a 'CacheContextRequestHeaderTest' block.
 *
 * @Block(
 *  id = "custom_cache_tags_cache_context_request_header_test",
 *  admin_label = @Translation("Cache context OS test"),
 * ) Как
 */
class CacheContextRequestHeaderTest extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function getOs(): string {
    $request_headers = \Drupal::service('request_stack')->getCurrentRequest()->headers;
    $user_agent = $request_headers->get('user-agent');
    if (preg_match('/linux/i', $user_agent)) {
      return 'Linux';
    }

    if (preg_match('/macintosh|mac os x/i', $user_agent)) {
      return 'Mac';
    }

    if (preg_match('/windows|win32/i', $user_agent)) {
      return 'Windows';
    }

    return 'other';
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $platform = $this->getOs();
    if ($platform === 'other') {
      return [
        '#markup' => t('Sorry, we have not already created software for you OS.'),
      ];
    }

    $external_link = Link::fromTextAndUrl(t('Download for @platform'), Url::fromUri('http://www.yoursite.com/', ['@platform' => $platform]))->toString();

    return [
      '#markup' => $external_link,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return ['custom_cache_tags_request_header:os'];
  }

}
