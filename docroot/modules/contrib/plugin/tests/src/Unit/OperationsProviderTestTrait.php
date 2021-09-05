<?php

namespace Drupal\Tests\plugin\Unit;
use Drupal\Core\Url;

/**
 * Provides assertions to test operations links integrity.
 */
trait OperationsProviderTestTrait {

  /**
   * Checks the integrity of operations links.
   *
   * @param mixed[] $operations_links
   */
  protected function assertOperationsLinks(array $operations_links) {
    foreach ($operations_links as $link) {
      \PHPUnit\Framework\Assert::assertArrayHasKey('title', $link);
      \PHPUnit\Framework\Assert::assertNotEmpty($link['title']);

      \PHPUnit\Framework\Assert::assertArrayHasKey('url', $link);
      \PHPUnit\Framework\Assert::assertInstanceOf(Url::class, $link['url']);
    }
  }

}
