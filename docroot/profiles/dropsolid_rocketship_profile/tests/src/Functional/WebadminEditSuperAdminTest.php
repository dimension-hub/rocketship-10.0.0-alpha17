<?php

namespace Drupal\Tests\dropsolid_rocketship_profile\Functional;

/**
 * Test to check that a webadmin-user CANNOT create superadmins.
 *
 * @group dropsolid_rocketship_profile
 * @group rocketship
 * @group security
 */
class WebadminEditSuperAdminTest extends RocketshipBrowserTestBase {

  /**
   * Tests that the webadmin can not edit the super admin.
   */
  public function testEditSuperAdmin() {
    $this->drupalLoginAsWebAdmin();
    $this->drupalGet('user/1/edit');
    $this->assertSession()->statusCodeEquals(403);
    $this->drupalGet('user/2/edit');
    $this->assertSession()->statusCodeEquals(200);
  }

}
