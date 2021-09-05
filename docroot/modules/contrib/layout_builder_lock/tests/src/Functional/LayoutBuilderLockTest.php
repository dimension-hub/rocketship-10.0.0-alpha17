<?php

namespace Drupal\Tests\layout_builder_lock\Functional;

use Drupal\block_content\Entity\BlockContentType;
use Drupal\Core\Entity\EntityStorageException;
use Drupal\layout_builder\Entity\LayoutBuilderEntityViewDisplay;
use Drupal\layout_builder_lock\LayoutBuilderLock;
use Drupal\node\NodeInterface;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\WebAssert;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests Layout Builder Lock.
 *
 * @group layout_builder_lock
 */
class LayoutBuilderLockTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'layout_builder',
    'layout_builder_lock',
    'block_content',
    'block',
    'node',
    'user',
  ];

  /**
   * The body field uuid.
   *
   * @var string
   */
  protected $body_field_block_uuid;

  /**
   * The custom default block uuid.
   *
   * @var string
   */
  protected $custom_default_block_uuid;

  /**
   * The editor block uuid.
   *
   * @var string
   */
  protected $custom_editor_block_uuid;

  /**
   * The default theme to use.
   *
   * @var string
   */
  protected $defaultTheme = 'stark';

  /**
   * A user with all permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * A user with all permissions except bypass.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUserNoBypass;

  /**
   * A user with default permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editor;

  /**
   * A user which can override lock settings overrides.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $editorOverride;

  /**
   * The default editor permissions.
   *
   * @var array
   */
  protected $editorPermissions = [
    'bypass node access',
    'configure any layout',
    'create and edit custom blocks',
    'access contextual links',
  ];

  /**
   * The editor permissions.
   *
   * @var array
   */
  protected $editorOverridePermissions = [
    'bypass node access',
    'configure any layout',
    'create and edit custom blocks',
    'access contextual links',
    'manage lock settings on overrides',
  ];

  /**
   * The editor permissions.
   *
   * @var array
   */
  protected $adminUserNoBypassPermissions = [
    'bypass node access',
    'configure any layout',
    'create and edit custom blocks',
    'access contextual links',
    'administer node display',
    'manage lock settings on sections',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    // Enable Layout Builder for landing page.
    $this->createContentType(['type' => 'landing_page']);

    $bundle = BlockContentType::create([
      'id' => 'basic',
      'label' => 'Basic',
      'revision' => FALSE,
    ]);
    $bundle->save();
    block_content_add_body_field($bundle->id());

    LayoutBuilderEntityViewDisplay::load('node.landing_page.default')
      ->enableLayoutBuilder()
      ->setOverridable()
      ->save();

    try {
      $this->adminUser = $this->createUser([], 'administrator', TRUE);
    } catch (EntityStorageException $ignored) {}
    try {
      $this->adminUserNoBypass = $this->createUser($this->adminUserNoBypassPermissions, 'administratorNoByPass');
    } catch (EntityStorageException $ignored) {}
    try {
      $this->editor = $this->createUser($this->editorPermissions, 'editor');
    } catch (EntityStorageException $ignored) {}
    try {
      $this->editorOverride = $this->createUser($this->editorOverridePermissions, 'editorOverride');
    } catch (EntityStorageException $ignored) {}
  }

  /**
   * Tests locking features on sections.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLock() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Create first node.
    $node_1 = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Homepage 1']);

    // Check as editor.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node_1->id() . '/layout');

    // Get the block uuid from the body field.
    $id = $assert_session->elementExists('css', '.layout-builder__region > div:nth-child(3)');
    $this->body_field_block_uuid = $id->getAttribute('data-layout-block-uuid');

    // Check links and access.
    $this->checkLinksAndAccess($assert_session, $node_1);

    // Configure the section locks.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/layout_builder/configure/section/defaults/node.landing_page.default/0');

    $edit = [];
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_BLOCK_ADD . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_BLOCK_MOVE . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_BLOCK_UPDATE . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_BLOCK_DELETE . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BEFORE . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BLOCK_MOVE . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_AFTER . ']'] = TRUE;
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']'] = TRUE;
    $this->drupalPostForm(NULL, $edit, 'Update');
    $page->pressButton('Save layout');

    // Create second node.
    $node_2 = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Homepage 2']);

    // Check as editor.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node_2->id() . '/layout');
    $this->checkLinksAndAccess($assert_session, $node_2, TRUE,403);

    // Links will still exist on node 1 as the overridden settings are used.
    $this->drupalGet('node/' . $node_1->id() . '/layout');
    $this->checkLinksAndAccess($assert_session, $node_1);

    // Override per entity is allowed for administrators.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('layout_builder/configure/section/overrides/node.' . $node_2->id() . '/0');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Lock settings');

    // Check if a user can manage override.
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node_2->id() . '/layout');
    $assert_session->responseNotContains('Configure section 1');
    $this->drupalGet('layout_builder/configure/section/overrides/node.' . $node_2->id() . '/0');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->editorOverride);
    $this->drupalGet('node/' . $node_2->id() . '/layout');
    $assert_session->responseContains('Configure section 1');
    $this->drupalGet('layout_builder/configure/section/overrides/node.' . $node_2->id() . '/0');
    $assert_session->statusCodeEquals(200);

    // Override settings on override.
    $node_3 = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Homepage 3']);
    $this->drupalGet('node/' . $node_3->id() . '/layout');
    $assert_session->statusCodeEquals(200);
    $this->drupalGet('layout_builder/configure/section/overrides/node.' . $node_3->id() . '/0');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Lock settings');
    $edit = [];
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_AFTER . ']'] = FALSE;
    $this->drupalPostForm(NULL, $edit, 'Update');
    $page->pressButton('Save layout');

    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node_3->id() . '/layout');
    $this->checkLinksAndAccess($assert_session, $node_3, TRUE, 403, TRUE);

    // Try to add a new section.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/1/layout_onecol');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Locks can be configured when the section has been added.');
    $this->drupalPostForm(NULL, [], 'Add section');
    $assert_session->statusCodeEquals(200);

    // Test the 'bypass lock settings on layout overrides', in combination
    // with 'manage lock settings on sections'. In this case, the user does not
    // have the permission to do anything on the override.
    $this->drupalLogin($this->adminUserNoBypass);
    $this->drupalGet('/layout_builder/configure/section/defaults/node.landing_page.default/0');
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Lock settings');
    $this->drupalGet('node/' . $node_2->id() . '/layout');
    $this->checkLinksAndAccess($assert_session, $node_2, TRUE,403);

    // Check custom inline block can be updated in a section that is configured
    // to allow adding new blocks and not allowing updating default blocks.
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('/layout_builder/configure/section/defaults/node.landing_page.default/0');
    $edit = [];
    $edit['layout_builder_lock[' . LayoutBuilderLock::LOCKED_BLOCK_ADD . ']'] = FALSE;
    $this->drupalPostForm(NULL, $edit, 'Update');
    $this->drupalGet('/layout_builder/add/block/defaults/node.landing_page.default/0/content/inline_block:basic');
    $edit = [];
    $edit['settings[label]'] = 'Default custom block title';
    $edit['settings[block_form][body][0][value]'] = 'Default custom block content';
    $this->drupalPostForm(NULL, $edit, 'Add block');

    // Get the block uuid from the custom block.
    $id = $assert_session->elementExists('css', '.layout-builder__region > div:nth-child(4)');
    $this->custom_default_block_uuid = $id->getAttribute('data-layout-block-uuid');
    $page->pressButton('Save layout');
    $this->drupalGet('/layout_builder/configure/section/defaults/node.landing_page.default/0');
    $assert_session->checkboxNotChecked('layout_builder_lock[4]');
    $this->drupalGet('/layout_builder/update/block/defaults/node.landing_page.default/0/content/' . $this->custom_default_block_uuid);
    $assert_session->statusCodeEquals(200);
    $assert_session->responseContains('Default custom block content');

    // Check as editor.
    $node_4 = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Landing page 2']);
    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node_4->id() . '/layout');
    $assert_session->responseContains('Default custom block content');
    $assert_session->linkExists('Add block');
    $this->drupalGet('/layout_builder/update/block/overrides/node.' . $node_4->id() . '/0/content/' . $this->custom_default_block_uuid);
    $assert_session->statusCodeEquals(403);

    // Add custom block as editor
    $this->drupalGet('/layout_builder/add/block/overrides/node.' . $node_4->id() . '/0/content/inline_block:basic');
    $edit = [];
    $edit['settings[label]'] = 'Editor block title';
    $edit['settings[block_form][body][0][value]'] = 'Editor block content';
    $this->drupalPostForm(NULL, $edit, 'Add block');
    $id = $assert_session->elementExists('css', '.layout-builder__region > div:nth-child(5)');
    $this->custom_editor_block_uuid = $id->getAttribute('data-layout-block-uuid');
    $page->pressButton('Save layout');
    $assert_session->responseContains('Editor block content');
    $this->drupalGet('/layout_builder/update/block/overrides/node.' . $node_4->id() . '/0/content/' . $this->custom_editor_block_uuid);
    $assert_session->statusCodeEquals(200);
  }

  /**
   * Tests with at least 3 sections.
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testMultipleSections() {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Ensure the lock settings are added to the correct section when adding a
    // new section before an existing section.
    // We expect that:
    //  - we can't add lock settings for new sections,
    //    @see https://www.drupal.org/project/layout_builder_lock/issues/3129009
    //  - the settings are stored on the correct section
    $this->drupalLogin($this->adminUser);
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/0/layout_onecol');
    $assert_session->responseContains('Locks can be configured when the section has been added.');

    // Add a new section above default
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/0/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'section above default'], 'Add section');
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/0');
    $this->drupalPostForm(NULL, ['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']' => TRUE], 'Update');

    // Add a new section between previous created and default
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/1/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'new section in between'], 'Add section');
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/1');
    $this->drupalPostForm(NULL, ['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BEFORE . ']' => TRUE], 'Update');

    $page->pressButton('Save layout');

    // we expect that the first section (first added) has the `locked section configure` checkbox checked
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/0');
    $assert_session->checkboxChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']');
    $assert_session->checkboxNotChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BEFORE . ']');

    // we expect that the second section (last added) has the `locked section before` checkbox checked
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/1');
    $assert_session->checkboxChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BEFORE . ']');
    $assert_session->checkboxNotChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']');

    // we expect that the third section (default) has no checkboxes checked
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/2');
    $assert_session->checkboxNotChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']');
    $assert_session->checkboxNotChecked('layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_BEFORE . ']');

    // Create a node.
    $node = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Homepage']);

    // Simply login as an editor. Should not throw any PHP error
    // @see https://www.drupal.org/project/layout_builder_lock/issues/3121250

    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node->id() . '/layout');

    $assert_session->linkByHrefExists('/layout_builder/choose/section/overrides/node.' . $node->id() . '/0');
    $assert_session->linkByHrefNotExists('/layout_builder/choose/section/overrides/node.' . $node->id() . '/1');
    $assert_session->linkByHrefExists('/layout_builder/choose/section/overrides/node.' . $node->id() . '/2');

    $assert_session->linkExists('Configure new section in between');
    $assert_session->linkExists('Configure Section 3');

    // Ensure sections with empty lock config don't mess up the subsequent
    // 'configure section' links.
    $this->drupalLogin($this->adminUser);

    // Add a section without config so it has a section delta > 1.
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/2/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'section without any lock config'], 'Add section');

    // Add extra sections that have section configuration locked
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/3/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'section with locked section configuration  1'], 'Add section');
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/3');
    $this->drupalPostForm(NULL, ['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']' => TRUE, 'layout_settings[label]' => 'section with locked section configuration  1'], 'Update');

    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/5/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'section with locked section configuration  2'], 'Add section');
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/5');
    $this->drupalPostForm(NULL, ['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']' => TRUE, 'layout_settings[label]' => 'section with locked section configuration  2'], 'Update');

    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/6/layout_onecol');
    $this->drupalPostForm(NULL, ['layout_settings[label]' => 'section with locked section configuration  3'], 'Add section');
    $this->drupalGet('layout_builder/configure/section/defaults/node.landing_page.default/6');
    $this->drupalPostForm(NULL, ['layout_builder_lock[' . LayoutBuilderLock::LOCKED_SECTION_CONFIGURE . ']' => TRUE, 'layout_settings[label]' => 'section with locked section configuration 3'], 'Update');

    $page->pressButton('Save layout');

    // Create a node.
    $node = $this->drupalCreateNode(['type' => 'landing_page', 'title' => 'Homepage']);

    $this->drupalLogin($this->editor);
    $this->drupalGet('node/' . $node->id() . '/layout');

    $assert_session->linkNotExists('Configure section above default');
    $assert_session->linkExists('Configure new section in between');
    $assert_session->linkExists('Configure section without any lock config');
    $assert_session->linkNotExists('Configure section with locked configure 1');
    $assert_session->linkExists('Configure Section 5');
    $assert_session->linkNotExists('Configure section with locked configure 2');
    $assert_session->linkNotExists('Configure section with locked configure 3');
  }

  /**
   * Checks links and access.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   * @param \Drupal\node\NodeInterface $node
   * @param bool $locked
   * @param int $code
   * @param null $allow_section_after
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function checkLinksAndAccess(WebAssert $assert_session, NodeInterface $node, $locked = FALSE, $code = 200, $allow_section_after = NULL) {
    if ($code == 200) {
      $assert_session->linkExists('Add block');
      $assert_session->linkExists('Add section');
      $assert_session->linkExists('Remove Section 1');
      $assert_session->linkExists('Configure Section 1');
      $assert_session->responseContains('js-layout-builder-block');
      $assert_session->responseContains('js-layout-builder-region');
    }
    else {
      if ($allow_section_after) {
        $assert_session->linkExists('Add section');
      }
      else {
        $assert_session->linkNotExists('Add section');
      }
      $assert_session->linkNotExists('Add block');
      $assert_session->linkNotExists('Remove Section 1');
      $assert_session->linkNotExists('Configure Section 1');
      $assert_session->responseNotContains('js-layout-builder-block');
      $assert_session->responseNotContains('js-layout-builder-region');
    }
    $this->checkContextualLinks($assert_session, $locked);
    $this->checkRouteAccess($assert_session, $node, $code, $allow_section_after);
  }

  /**
   * Checks access to routes related to layout builder.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   * @param \Drupal\node\NodeInterface $node
   * @param int $code
   * @param null $section_after
   *
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  protected function checkRouteAccess(WebAssert $assert_session, NodeInterface $node, $code = 200, $section_after = NULL) {

    $paths = [
      'layout_builder/configure/section/overrides/node.' . $node->id() . '/0',
      'layout_builder/remove/section/overrides/node.' . $node->id() . '/0',
      'layout_builder/choose/section/overrides/node.' . $node->id() . '/0',
      'layout_builder/choose/section/overrides/node.' . $node->id() . '/1',
      'layout_builder/choose/block/overrides/node.' . $node->id() . '/0/content',
      'layout_builder/update/block/overrides/node.' . $node->id() . '/0/content/' . $this->body_field_block_uuid,
      'layout_builder/move/block/overrides/node.' . $node->id() . '/0/content/' . $this->body_field_block_uuid,
      'layout_builder/remove/block/overrides/node.' . $node->id() . '/0/content/' . $this->body_field_block_uuid,
    ];
    foreach ($paths as $path) {
      $this->drupalGet($path);

      if ($section_after && $path == 'layout_builder/choose/section/overrides/node.' . $node->id() . '/1') {
        $assert_session->statusCodeEquals(200);
      }
      else {
        $assert_session->statusCodeEquals($code);
      }

    }
  }

  /**
   * Check contextual links.
   *
   * @param \Drupal\Tests\WebAssert $assert_session
   * @param bool $locked
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   */
  protected function checkContextualLinks(WebAssert $assert_session, $locked = FALSE) {
    // Parse contextual links - target body field.
    $id = $assert_session->elementExists('css', '.layout-builder__region > div:nth-child(3) > div');
    $value = $id->getAttribute('data-contextual-id');
    $has_layout_builder_lock_element = FALSE;
    $layout_builder_lock_elements = $layout_builder_block_elements = [];

    $elements = explode('&', $value);
    foreach ($elements as $element) {

      // Layout Builder Lock element.
      if (strpos($element, 'layout_builder_lock') !== FALSE) {
        $has_layout_builder_lock_element = TRUE;
        $layout_builder_lock_elements = explode(':', str_replace(['%3A', 'layout_builder_lock='], [':', ''], $element));
      }

      // Layout Builder Block elements.
      if (strpos($element, 'operations') !== FALSE) {
        $ex = explode(':', $element, 2);
        $layout_builder_block_elements = explode(':', str_replace(['%3A', 'operations='], [':', ''], $ex[1]));
      }
    }

    if ($locked) {
      if ($has_layout_builder_lock_element) {
        self::assertTrue(in_array('layout_builder_block_move', $layout_builder_lock_elements));
        self::assertTrue(!in_array('move', $layout_builder_block_elements));
        self::assertTrue(in_array('layout_builder_block_update', $layout_builder_lock_elements));
        self::assertTrue(!in_array('update', $layout_builder_block_elements));
        self::assertTrue(in_array('layout_builder_block_remove', $layout_builder_lock_elements));
        self::assertTrue(!in_array('remove', $layout_builder_block_elements));
      }
      else {
        // Trigger an explicit fail.
        self::assertTrue($has_layout_builder_lock_element);
      }
    }
    else {
      self::assertTrue(empty($has_layout_builder_lock_element));
      self::assertTrue(in_array('move', $layout_builder_block_elements));
      self::assertTrue(in_array('update', $layout_builder_block_elements));
      self::assertTrue(in_array('remove', $layout_builder_block_elements));
    }
  }

}
