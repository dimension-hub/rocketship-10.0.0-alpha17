<?php

namespace Drupal\dumserv;

/**
 * Class RandomMessageGenerator.
 *
 * @package Drupal\dumserv
 */
class RandomMessageGenerator {

  /**
   * Массив с сообщениями.
   */
  private $messages;

  /**
   * {@inheritdoc}
   */
  public function __construct() {
    // Записываем сообщения в свойство.
    $this->setMessages();
  }

  /**
   * Здесь мы просто задаем все возможные варианты сообщений.
   */
  private function setMessages(): void {
    $this->messages = [
      'Lorem ipsum dolor sit amet, consecrated advising elite.',
      'Phallus maximus incident dolor et Matrices.',
      'Maces vitae null sed fells faucets ostriches. Suspense potent.',
      'In nec orc vitae deque rhonchus rhonchus eu vel erat.',
      'Done subscript consequent ex, at ostriches mi venerates ut.',
      'Fuse nib erat, aliquot non metas quits, mattes elementum nib. Nulls output ante non torpor laureate bandit.',
      'Suspense et nun id Caligula interim malady.',
    ];
  }

  /**
   * Метод, который возвра.
   *
   * @throws \Exception
   */
  public function getRandomMessage() {
    $random = random_int(0, count($this->messages));
    return $this->messages[$random];
  }

}
