<?php

namespace Drupal\custom_cache_tags\Cache\Context;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\Core\Cache\Context\CalculatedCacheContextInterface;
use Drupal\Core\Cache\Context\RequestStackCacheContextBase;

/**
 * Cache context ID: 'custom_cache_tags_request_header'.
 */
class CustomCacheTagsRequestHeaderCacheContext extends RequestStackCacheContextBase implements CalculatedCacheContextInterface {

  /**
   * {@inheritdoc}
   */
  public static function getLabel() {
    return t('Cache Tags request header');
  }

  /**
   * {@inheritdoc}
   */
  public function getContext($parameter = NULL): string {
    $request_headers = $this->requestStack->getCurrentRequest()->headers;
    if ($parameter) {
      if ($request_headers->has($parameter)) {
        return (string) $request_headers->get($parameter);
      }

      if ($parameter === 'os') {
        $user_agent = $request_headers->get('user-agent');
        if (preg_match('/linux/i', $user_agent)) {
          return 'linux';
        }

        if (preg_match('/macintosh|mac os x/i', $user_agent)) {
          return 'mac';
        }

        if (preg_match('/windows|win32/i', $user_agent)) {
          return 'windows';
        }

        return 'other';
      }

      return '';
    }

    // If none parameter is passed, we get all available during request and
    // merges them into single string, after that we hash it with md5 and
    // return result.
    $headers_string = implode(';', array_map(static function ($entry) {
      return $entry[0];
    }, $request_headers->all()));
    return md5($headers_string);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheableMetadata($parameter = NULL): CacheableMetadata {
    return new CacheableMetadata();
  }

}
