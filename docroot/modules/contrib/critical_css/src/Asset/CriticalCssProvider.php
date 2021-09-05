<?php

namespace Drupal\critical_css\Asset;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Routing\ResettableStackedRouteMatchInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Path\CurrentPathStack;
use Drupal\Core\Theme\ThemeManagerInterface;

/**
 * Critical CSS Provider.
 *
 * Calculate which CSS file must be used for Critical CSS based on current
 * request (entity id, path, bundle name, etc).
 */
class CriticalCssProvider implements CriticalCssProviderInterface {

  /**
   * Critical CSS config.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Current Route Match.
   *
   * @var \Drupal\Core\Routing\ResettableStackedRouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Current path stack.
   *
   * @var \Drupal\Core\Path\CurrentPathStack
   */
  protected $currentPathStack;

  /**
   * Current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * Theme Manager.
   *
   * @var \Drupal\Core\Theme\ThemeManagerInterface
   */
  protected $themeManager;

  /**
   * The route admin context to determine whether a route is an admin one.
   *
   * @var \Drupal\Core\Routing\AdminContext
   */
  protected $adminContext;

  /**
   * Flag set when this request has already been processed.
   *
   * @var bool
   */
  protected $isAlreadyProcessed;

  /**
   * Critical CSS data to be inlined.
   *
   * @var string
   */
  protected $criticalCss;

  /**
   * Possible file paths to find CSS contents.
   *
   * @var array
   */
  protected $filePaths = [];

  /**
   * File used for critical CSS.
   *
   * @var string
   */
  protected $matchedFilePath;

  /**
   * CriticalCssProvider constructor.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   Request.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Routing\ResettableStackedRouteMatchInterface $current_route_match
   *   Current route.
   * @param \Drupal\Core\Path\CurrentPathStack $current_path_stack
   *   Current path.
   * @param \Drupal\Core\Session\AccountProxyInterface $current_user
   *   Current user.
   * @param \Drupal\Core\Theme\ThemeManagerInterface $theme_manager
   *   The Theme Manager.
   * @param \Drupal\Core\Routing\AdminContext $admin_context
   *   The route admin context service.
   */
  public function __construct(
    ModuleHandlerInterface $module_handler,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
    ResettableStackedRouteMatchInterface $current_route_match,
    CurrentPathStack $current_path_stack,
    AccountProxyInterface $current_user,
    ThemeManagerInterface $theme_manager,
    AdminContext $admin_context
  ) {
    $this->moduleHandler = $module_handler;
    $this->request = $request_stack->getCurrentRequest();
    $this->config = $config_factory->get('critical_css.settings');
    $this->currentRouteMatch = $current_route_match;
    $this->currentPathStack = $current_path_stack;
    $this->currentUser = $current_user;
    $this->themeManager = $theme_manager;
    $this->adminContext = $admin_context;
  }

  /**
   * {@inheritdoc}
   */
  public function getCriticalCss() {
    // Return previous result, if any.
    if ($this->isAlreadyProcessed) {
      return $this->criticalCss;
    }

    $this->isAlreadyProcessed = TRUE;

    // Get possible file paths and return first match.
    $filePaths = $this->getFilePaths();
    foreach ($filePaths as $filePath) {
      if (is_file($filePath)) {
        $this->matchedFilePath = $filePath;
        $this->criticalCss = trim(file_get_contents($filePath));
        break;
      }
    }
    return $this->criticalCss;
  }

  /**
   * {@inheritdoc}
   *
   * When accessing an admin route, this module is always disabled to avoid
   * multiple problems with Drupal's admin theme. On the other hand, this is a
   * module aimed to be used for a site's frontend, and not for the backend.
   */
  public function isEnabled() {
    $route = $this->currentRouteMatch->getRouteObject();
    if ($route && $this->adminContext->isAdminRoute($route)) {
      return FALSE;
    }
    return (bool) $this->config->get('enabled');
  }

  /**
   * Check if module is enabled for logged-in users.
   *
   * @return bool
   *   True if this module is enabled for logged-in users.
   */
  protected function isEnabledForLoggedInUsers() {
    return (bool) $this->config->get('enabled_for_logged_in_users');
  }

  /**
   * Check if entity id is excluded by configuration.
   *
   * @param int $entityId
   *   Entity ID (integer).
   *
   * @return bool
   *   True if entity is excluded.
   */
  protected function isEntityIdExcluded($entityId) {
    $excludedIds = explode("\n", $this->config->get('excluded_ids'));
    $excludedIds = array_map(function ($item) {
      return trim($item);
    }, $excludedIds);
    return (
      is_array($excludedIds) &&
      in_array($entityId, $excludedIds)
    );
  }

  /**
   * Get critical CSS file path by a key (id, string, etc).
   *
   * @param string $key
   *   Key to search.
   *
   * @return string
   *   Critical CSS string.
   */
  protected function getFilePathByKey($key) {
    if (empty($key)) {
      return NULL;
    }

    $criticalCssDirPath = str_replace(
      '..',
      '',
      $this->config->get('dir_path')
    );
    $themePath = $this->themeManager->getActiveTheme()->getPath();
    $criticalCssDir = $themePath . $criticalCssDirPath;

    return $criticalCssDir . '/' . $key . '.css';
  }

  /**
   * {@inheritdoc}
   */
  public function getFilePaths() {
    if (!$this->filePaths) {
      $this->filePaths = $this->calculateFilePaths();
    }
    return $this->filePaths;
  }

  /**
   * Get all possible paths to search, relatives to theme.
   *
   * @return array
   *   Array with all possible paths.
   */
  protected function calculateFilePaths() {
    // Opt out if module is disabled.
    if (!$this->isEnabled()) {
      return [];
    }

    // Check if module is enabled for logged-in users.
    if (!$this->currentUser->isAnonymous() && !$this->isEnabledForLoggedInUsers()) {
      return [];
    }

    // Get current entity's data.
    $entity = $this->getCurrentEntity();
    $entityId = NULL;
    $bundleName = NULL;
    if (is_object($entity) && method_exists($entity, 'id') && method_exists($entity, 'bundle')) {
      $entityId = $entity->id();
      $bundleName = $entity->bundle();
    }

    // Check if this entity id is excluded.
    if ($entityId && $this->isEntityIdExcluded($entityId)) {
      return [];
    }

    // Get sanitized path, which is something like /node/{X}.
    $sanitizedPath = $this->sanitizePath($this->currentPathStack->getPath());

    // Get sanitized path info, which is something like /article/{title}.
    $sanitizedPathInfo = $this->sanitizePath($this->request->getPathInfo());

    // Get all possible paths in order, starting with the most specific ones
    // (entity id) and finishing with a fallback. Between them, use a "path-"
    // prefix to avoid collisions when there is a node and a bundle with the
    // same name.
    $filePaths[] = $this->getFilePathByKey($entityId);
    $filePaths[] = $this->getFilePathByKey('path-' . $sanitizedPath);
    $filePaths[] = $this->getFilePathByKey($sanitizedPath);
    $filePaths[] = $this->getFilePathByKey('path-' . $sanitizedPathInfo);
    $filePaths[] = $this->getFilePathByKey($sanitizedPathInfo);
    $filePaths[] = $this->getFilePathByKey($bundleName);
    $filePaths[] = $this->getFilePathByKey('default-critical');

    // Remove all null paths (if no callback is supplied to array_filter, all
    // entries of array equal to FALSE are removed)
    $filePaths = array_filter($filePaths);

    // Remove repeated paths.
    $filePaths = array_unique($filePaths);

    // Allow other modules to alter file paths array.
    $this->moduleHandler->alter('critical_css_file_paths_suggestion', $filePaths, $entity);

    return $filePaths;
  }

  /**
   * Get current entity.
   *
   * At this moment, it only works for nodes and taxonomy terms.
   *
   * @return \Drupal\Core\Entity\ContentEntityInterface|null
   *   Matched file path, or null if nothing found.
   */
  protected function getCurrentEntity() {
    $entityBundles = ['node', 'taxonomy_term'];
    foreach ($entityBundles as $entityBundle) {
      $entity = $this->currentRouteMatch->getParameter($entityBundle);
      if ($entity) {
        return $entity;
      }
    }
    return NULL;
  }

  /**
   * Sanitizes a path so its usable as a filename.
   *
   * @return string
   *   The sanitized path.
   */
  protected function sanitizePath($path) {
    $path = preg_replace("/^\//", "", $path);
    $path = preg_replace("/[^a-zA-Z0-9\/-]/", "", $path);
    $path = str_replace("/", "-", $path);
    if (empty($path)) {
      $path = 'front';
    }
    return $path;
  }

  /**
   * Get matched file path.
   *
   * @return string|null
   *   Matched file path, or null if nothing found.
   */
  public function getMatchedFilePath() {
    // Ensure $this->getCriticalCss() is called before returning anything.
    if (!$this->isAlreadyProcessed) {
      $this->getCriticalCss();
    }

    return $this->matchedFilePath;
  }

  /**
   * {@inheritdoc}
   */
  public function isAlreadyProcessed() {
    return $this->isAlreadyProcessed;
  }

  /**
   * {@inheritdoc}
   */
  public function reset() {
    $this->isAlreadyProcessed = FALSE;
    $this->filePaths = [];
    $this->matchedFilePath = NULL;
  }

}
