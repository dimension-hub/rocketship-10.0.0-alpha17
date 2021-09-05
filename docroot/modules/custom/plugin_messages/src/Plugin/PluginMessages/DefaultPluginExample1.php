<?php

namespace Drupal\plugin_messages\Plugin\PluginMessages;

use Drupal\plugin_messages\PluginMessagesPluginBase;

/**
 * @PluginMessages(
 *   id="default_plugin_example_1",
 * )
 */
class DefaultPluginExample1 extends PluginMessagesPluginBase {

  /**
   * Возвращаем сообщение данного плагина.
   */
  public function getMessage(): string {
    return 'This is message from Example #1';
  }

}
