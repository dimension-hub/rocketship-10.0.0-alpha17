<?php

namespace Drupal\custom_migrate\Plugin\migrate\process;

use Drupal\migrate\Row;
use Drupal\migrate\MigrateExecutableInterface;
use Drupal\migrate\ProcessPluginBase;

/**
 * Return reversed string.
 *
 * @MigrateProcessPlugin(
 *   id = "strrev"
 * )
 */
class Strrev extends ProcessPluginBase {

  /**
   * {@inheritdoc}
   */
  public function transform($value, MigrateExecutableInterface $migrate_executable, Row $row, $destination_property) {
    return strrev($value);
  }

}
