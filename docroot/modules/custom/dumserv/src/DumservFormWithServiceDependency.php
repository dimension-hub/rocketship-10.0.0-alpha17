<?php

namespace Drupal\dumserv;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class DumservFormWithServiceDependency.
 *
 * @package Drupal\dumserv
 */
class DumservFormWithServiceDependency extends FormBase {

  protected $random_message_generator;
  protected $random_drupal_message;

  /**
   * DumservFormWithServiceDependency constructor.
   *
   * В данный конструктор передаются экземпляры сервисов в том же самом порядке,
   * в каком они указаны в методе create. Соответственно там и указывается что
   * будет передано и загружено.
   *
   * @param \Drupal\dumserv\RandomMessageGenerator $random_message_generator
   * @param \Drupal\dumserv\RandomDrupalMessage $random_drupal_message
   */
  public function __construct(RandomMessageGenerator $random_message_generator, RandomDrupalMessage $random_drupal_message) {
    $this->random_message_generator = $random_message_generator;
    $this->random_drupal_message = $random_drupal_message;
  }

  /**
   * В данном методе мы указываем все нужные нам сервисы.
   */
  public static function create(ContainerInterface $container): DumservFormWithServiceDependency {
    // Передаваться они будут в соответствующем порядке.
    return new static(
      $container->get('dumserv.random_message'),
      $container->get('dumserv.random_drupal_message')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId(): string {
    return 'dumserv_form_with_service_dependecy';
  }

  /**
   * {@inheritdoc}.
   *
   * @throws \Exception
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->random_drupal_message->setRandomMessage();

    $form['random_message'] = [
      '#markup' => $this->random_message_generator->getRandomMessage(),
    ];

    return $form;
  }

  /**
   *
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
  }

}
