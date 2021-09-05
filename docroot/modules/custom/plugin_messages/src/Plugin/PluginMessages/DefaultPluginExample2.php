<?php

namespace Drupal\plugin_messages\Plugin\PluginMessages;

use Drupal\plugin_messages\PluginMessagesPluginBase;

/**
 * @PluginMessages(
 *   id="default_plugin_example_2",
 * )
 */
class DefaultPluginExample2 extends PluginMessagesPluginBase {

  /**
   * Возвращаем сообщение данного плагина.
   */
  public function getMessage(): string {
    return 'This is message from Example #2';
  }

  /**
   * {@inheritdoc}
   */
  public function getMessageType(): string {
    return 'error';
  }

  /**
   * {@inheritdoc}
   */
  public function getPages(): array {
    return [
      'node/*',
      'contact',
      '<front>',
    ];
  }

}
