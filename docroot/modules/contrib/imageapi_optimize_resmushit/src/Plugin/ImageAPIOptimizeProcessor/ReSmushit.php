<?php

namespace Drupal\imageapi_optimize_resmushit\Plugin\ImageAPIOptimizeProcessor;

use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\imageapi_optimize\ConfigurableImageAPIOptimizeProcessorBase;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Uses the resmush.it webservice to optimize an image.
 *
 * @ImageAPIOptimizeProcessor(
 *   id = "resmushit",
 *   label = @Translation("Resmush.it"),
 *   description = @Translation("Uses the free resmush.it service to optimize images.")
 * )
 */
final class ReSmushit extends ConfigurableImageAPIOptimizeProcessorBase {

  /**
   * The HTTP client to fetch the feed data with.
   *
   * @var \GuzzleHttp\ClientInterface
   */
  protected $httpClient;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, LoggerInterface $logger, ImageFactory $image_factory, ClientInterface $http_client) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $logger, $image_factory);

    $this->httpClient = $http_client;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('logger.factory')->get('imageapi_optimize'),
      $container->get('image.factory'),
      $container->get('http_client')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applyToImage($image_uri) {
    // Need to send the file off to the remote service and await a response.
    $fields[] = [
      'name' => 'files',
      'contents' => fopen($image_uri, 'r'),
    ];
    if (!empty($this->configuration['quality'])) {
      $fields[] = [
        'name' => 'qlty',
        'contents' => $this->configuration['quality'],
      ];
    }

    try {
      $response = $this->httpClient->post('http://api.resmush.it/ws.php', ['multipart' => $fields]);
      $body = $response->getBody();
      $json = json_decode($body);

      // If this has worked, we should get a dest entry in the JSON returned.
      if (isset($json->dest)) {
        // Now go fetch that, and save it locally.
        $smushedFile = $this->httpClient->get($json->dest);
        if ($smushedFile->getStatusCode() == 200) {
          \Drupal::service('file_system')->saveData($smushedFile->getBody(), $image_uri, FileSystemInterface::EXISTS_REPLACE);
          return TRUE;
        }
      }
    }
    catch (RequestException $e) {
      $this->logger->error('Failed to download optimize image using reSmush.it due to "%error".', ['%error' => $e->getMessage()]);
    }

    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'quality' => NULL,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    $form['quality'] = [
      '#type' => 'number',
      '#title' => $this->t('JPEG image quality'),
      '#description' => $this->t('Optionally specify a quality setting when optimizing JPEG images.'),
      '#default_value' => $this->configuration['quality'],
      '#min' => 1,
      '#max' => 100,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    parent::submitConfigurationForm($form, $form_state);

    $this->configuration['quality'] = $form_state->getValue('quality');
  }

}
