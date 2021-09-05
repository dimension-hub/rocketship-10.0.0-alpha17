<?php

namespace Drupal\critical_css\Asset;

use Drupal\Core\Asset\AssetCollectionRendererInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Render\Markup;
use Drupal\Component\Utility\Html;
use Drupal\Core\Template\TwigEnvironment;

/**
 * Decorates the CSS collection renderer service, adds Critical CSS.
 *
 * @see \Drupal\Core\Asset\CssCollectionRenderer
 */
class CssCollectionRenderer implements AssetCollectionRendererInterface {

  /**
   * The decorated CSS collection renderer.
   *
   * @var \Drupal\Core\Asset\AssetCollectionRendererInterface
   */
  protected $cssCollectionRenderer;

  /**
   * Config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * The Twig service.
   *
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  protected $twig;

  /**
   * Critical CSS provider.
   *
   * @var \Drupal\critical_css\Asset\CriticalCssProviderInterface
   */
  protected $criticalCssProvider;

  /**
   * Constructs a CssCollectionRenderer.
   *
   * @param \Drupal\Core\Asset\AssetCollectionRendererInterface $css_collection_renderer
   *   The decorated CSS collection renderer.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config Factory.
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   *   The Twig service.
   * @param \Drupal\critical_css\Asset\CriticalCssProviderInterface $critical_css_provider
   *   The Critical CSS provider.
   */
  public function __construct(AssetCollectionRendererInterface $css_collection_renderer, ConfigFactoryInterface $config_factory, TwigEnvironment $twig, CriticalCssProviderInterface $critical_css_provider) {
    $this->cssCollectionRenderer = $css_collection_renderer;
    $this->configFactory = $config_factory;
    $this->twig = $twig;
    $this->criticalCssProvider = $critical_css_provider;
  }

  /**
   * {@inheritdoc}
   */
  public function render(array $css_assets) {
    // Fixes https://www.drupal.org/project/critical_css/issues/2982651
    if (empty($css_assets)) {
      return [];
    }

    $css_assets = $this->cssCollectionRenderer->render($css_assets);
    $config = $this->configFactory->get('critical_css.settings');
    if (!$this->criticalCssProvider->isEnabled() || $this->criticalCssProvider->isAlreadyProcessed()) {
      return $css_assets;
    }

    $new_css_assets = [];
    // Add critical CSS asset. It will be added if a critical CSS file is found
    // or if Twig debug is enabled.
    $criticalCssAsset = $this->getCriticalCssAsset();
    if ($criticalCssAsset) {
      $new_css_assets[] = $criticalCssAsset;
    }

    // If a critical CSS is found, make other CSS files asynchronous.
    $criticalCss = $this->criticalCssProvider->getCriticalCss();
    if ($criticalCss) {
      $asyncAssets = $this->makeAssetsAsync($css_assets);
      $new_css_assets = array_merge($new_css_assets, $asyncAssets);
    }
    else {
      $new_css_assets = array_merge($new_css_assets, $css_assets);
    }

    return $new_css_assets;
  }

  /**
   * Get critical CSS element.
   *
   * It's an array that should be placed into $css_assets array from render()
   * method. It will contain date in two cases:
   * 1) When a critical CSS file is found.
   * 2) When Twig's debug is enabled.
   *
   * @return array|null
   *   Null if no critical CSS found nor Twig's debug is enabled. Otherwise,
   *   an array with the following items:
   *   * '#type'
   *   * '#tag'
   *   * '#attributes'
   *   * '#value'
   */
  protected function getCriticalCssAsset() {
    $criticalCssAsset = NULL;
    $debugInfo = $this->getDebugInfo();
    $criticalCss = $this->criticalCssProvider->getCriticalCss();
    if ($criticalCss || $debugInfo['start'] || $debugInfo['end']) {
      $criticalCssAsset = [
        '#type' => 'html_tag',
        '#tag' => 'style',
        '#attributes' => ['id' => 'critical-css'],
        '#value' => Markup::create($debugInfo['start'] . $criticalCss . $debugInfo['end']),
      ];
    }
    return $criticalCssAsset;
  }

  /**
   * Get critical CSS element.
   *
   * It's an array that should be placed into $css_assets array from render()
   * method.
   *
   * @param array $css_assets
   *   Array of CSS assets.
   *
   * @return array
   *   Array with two items (start and end string) ready to be used as debug
   *   info.
   */
  protected function makeAssetsAsync(array $css_assets) {
    $asyncAssets = [];
    foreach ($css_assets as $asset) {
      // Skip files with print media.
      if ($asset['#attributes']['media'] === 'print') {
        $asyncAssets[] = $asset;
      }
      else {
        if ($this->configFactory->get('critical_css.settings')
          ->get('preload_non_critical_css')) {
          // Add a preload link so non-critical CSS is loaded with the highest
          // priority.
          $preloadAsset = $asset;
          $preloadAsset['#attributes']['rel'] = 'preload';
          $preloadAsset['#attributes']['as'] = 'style';
          unset($preloadAsset['#attributes']['media']);
          $asyncAssets[] = $preloadAsset;
        }

        // Add a stylesheet link with print media and an "onload" event.
        // @see https://www.filamentgroup.com/lab/load-css-simpler/
        // TODO Find a better way to set the onload attribute:
        // Due to Drupal escaping quotes inside an attribute, we need to set a
        // dummy "data-onload-media" attribute, only needed for the onload
        // attribute.
        // "this.media='all'" gets escaped into
        // "this.media=&#039;all&#039;".
        $onLoadAsset = $asset;
        $onLoadAsset['#attributes']['media'] = 'print';
        $onLoadAsset['#attributes']['data-onload-media'] = 'all';
        $onLoadAsset['#attributes']['onload'] = 'this.onload=null;this.media=this.dataset.onloadMedia';
        $asyncAssets[] = $onLoadAsset;

        // Add fallback element for non-JS browsers.
        $noScriptAsset = $asset;
        $noScriptAsset['#noscript'] = TRUE;
        $asyncAssets[] = $noScriptAsset;
      }
    }

    return $asyncAssets;
  }

  /**
   * Get critical CSS debug info about current request.
   *
   * @return array
   *   Array with two items (start and end string) ready to be used as debug
   *   info.
   */
  protected function getDebugInfo() {
    $matchedFilePath = $this->criticalCssProvider->getMatchedFilePath();
    // Show debug info if twig debug is on.
    $debugInfoStart = NULL;
    $debugInfoEnd = NULL;
    if ($this->twig->isDebug()) {
      $filePaths = $this->criticalCssProvider->getFilePaths();
      $debugInfoStart = "\n/* CRITICAL CSS DEBUG */\n";
      $debugInfoStart .= "/* FILE NAME SUGGESTIONS:\n";
      foreach ($filePaths as $filePath) {
        $flag = ($filePath === $matchedFilePath) ? 'x' : '*';
        $debugInfoStart .= "\t $flag $filePath\n";
      }
      $debugInfoStart .= "*/\n";

      if ($matchedFilePath) {
        $debugInfoStart .= '/* BEGIN OUTPUT from ' . Html::escape($matchedFilePath) . " */\n";
        $debugInfoEnd = "\n/* END OUTPUT from " . Html::escape($matchedFilePath) . " */\n";
      }
      else {
        $debugInfoStart .= "/* NONE MATCHED. THIS USUALLY HAPPENS WHEN YOU ARE LOGGED IN. LOG OUT AND TRY AGAIN AS AN ANONYMOUS USER. */\n";
      }
    }

    return [
      'start' => $debugInfoStart,
      'end' => $debugInfoEnd,
    ];
  }

}
