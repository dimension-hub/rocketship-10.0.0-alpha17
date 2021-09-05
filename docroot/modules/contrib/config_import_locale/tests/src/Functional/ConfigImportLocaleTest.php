<?php

namespace Drupal\Tests\config_import_locale\Functional;

/**
 * Test functionality of config_import_locale module.
 *
 * @package Drupal\Tests\config_import_locale\Functional
 *
 * @group config_import_locale
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class ConfigImportLocaleTest extends ConfigImportLocaleTestBase {

  /**
   * Verify that the settings form works.
   */
  public function testSettingsForm() {
    // Login with a user that has permission to access the config import locale
    // settings.
    $this->drupalLogin($this->drupalCreateUser(['administer config import locale']));
    $edit = [
      'overwrite_interface_translation' => 'no_overwrite',
    ];
    $this->drupalGet('/admin/config/regional/translate/config-import-settings');
    $this->submitForm($edit, t('Save configuration'));

    $settings = $this->config('config_import_locale.settings')->get('overwrite_interface_translation');

    $this->assertEquals('no_overwrite', $settings);

  }

}
