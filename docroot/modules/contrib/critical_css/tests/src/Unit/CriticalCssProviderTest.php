<?php

namespace Drupal\Tests\critical_css\Unit;

use Drupal\Core\Extension\ModuleHandler;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\Core\Session\AccountProxy;
use Drupal\Core\Theme\ActiveTheme;
use Drupal\Core\Theme\ThemeManager;
use Drupal\critical_css\Asset\CriticalCssProvider;
use Drupal\Tests\UnitTestCase;
use ReflectionMethod;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Unit tests for CriticalCssProvider.
 *
 * This unit test is not really useful since there is a more powerful
 * Functional test. Anyway, we maintain it as a reference for future Unit tests.
 *
 * @coversDefaultClass \Drupal\critical_css\Asset\CriticalCssProvider
 * @group critical_css
 */
class CriticalCssProviderTest extends UnitTestCase {

  /**
   * The class under test.
   *
   * @var \Drupal\critical_css\Asset\CriticalCssProvider
   */
  protected $criticalCssProvider;

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    $config_factory = $this->getConfigFactoryStub(
      [
        'critical_css_enabled.settings' => [
          'enabled' => TRUE,
          'enabled_for_logged_in_users' => FALSE,
          'preload_non_critical_css' => FALSE,
          'dir_path' => "/css/critical",
          'excluded_ids' => NULL,
        ],
      ]
    );

    $module_handler = $this->createConfiguredMock(ModuleHandler::class, ['alter' => TRUE]);
    $request = $this->createConfiguredMock(Request::class, ['getPathInfo' => '/node/1']);
    $request_stack = $this->createConfiguredMock(RequestStack::class, ['getCurrentRequest' => $request]);
    $current_route_match = $this->createConfiguredMock(CurrentRouteMatch::class, ['getParameter' => NULL]);
    $current_path_stack = $this->createConfiguredMock(CurrentPathStack::class, ['getPath' => '/article/title']);
    $current_user = $this->createConfiguredMock(AccountProxy::class, ['isAnonymous' => TRUE]);
    $active_theme = $this->createConfiguredMock(ActiveTheme::class, ['getPath' => 'core/themes/bartik']);
    $theme_manager = $this->createConfiguredMock(ThemeManager::class, ['getActiveTheme' => $active_theme]);
    $admin_context = $this->createConfiguredMock(AdminContext::class, ['isAdminRoute' => FALSE]);

    $this->criticalCssProvider = new CriticalCssProvider($module_handler, $request_stack, $config_factory, $current_route_match, $current_path_stack, $current_user, $theme_manager, $admin_context);
  }

  /**
   * Tests sanitizePath.
   *
   * @covers ::sanitizePath
   * @dataProvider sanitizePathProvider
   *
   * @throws \ReflectionException
   */
  public function testSanitizePath($rawPath, $sanitizedPath) {
    $method = new ReflectionMethod($this->criticalCssProvider, "sanitizePath");
    $method->setAccessible(TRUE);
    $this->assertEquals($method->invoke($this->criticalCssProvider, $rawPath), $sanitizedPath);
  }

  /**
   * Data provider for testSanitizePath.
   */
  public function sanitizePathProvider() {
    return [
      ["/node/1", "node-1"],
      ["/node /1ñá", "node-1"],
      ["/node-/1", "node--1"],
      ["/", "front"],
    ];
  }

}
