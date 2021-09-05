<?php

namespace Drupal\KernelTests\Core\Entity;

use Drupal\Core\TypedData\TranslationStatusInterface;
use Drupal\entity_test\Entity\EntityTestMul;
use Drupal\entity_test\Entity\EntityTestRev;
use Drupal\language\Entity\ConfigurableLanguage;

/**
 * Test entity duplication.
 *
 * @group Entity
 */
class EntityDuplicateTest extends EntityKernelTestBase {

  /**
   * {@inheritdoc}
   */
  public static $modules = ['language', 'entity_test'];

  /**
   * @var \Drupal\Core\Entity\ContentEntityStorageInterface
   */
  protected $entityTestRevStorage;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Enable an additional language.
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('nl')->save();

    $this->installEntitySchema('entity_test_rev');
    $this->installEntitySchema('entity_test_mul');
    $this->entityTestRevStorage = $this->container->get('entity_type.manager')->getStorage('entity_test_rev');
  }

  /**
   * Tests duplicating a non-default revision.
   */
  public function testDuplicateNonDefaultRevision() {
    $entity = EntityTestRev::create([
      'name' => 'First Revision',
    ]);
    $entity->save();
    $first_revision_id = $entity->getRevisionId();

    $entity->setNewRevision(TRUE);
    $entity->name = 'Second Revision';
    $entity->save();

    $duplicate_first_revision = $this->entityTestRevStorage->loadRevision($first_revision_id)->createDuplicate();
    $this->assertTrue($duplicate_first_revision->isDefaultRevision(), 'Duplicating a non-default revision creates a default revision.');
    $this->assertEquals('First Revision', $duplicate_first_revision->label());
    $duplicate_first_revision->save();

    $duplicate_first_revision->name = 'Updated name';
    $duplicate_first_revision->save();

    $this->entityTestRevStorage->resetCache();
    $duplicate_first_revision = EntityTestRev::load($duplicate_first_revision->id());
    $this->assertEquals('Updated name', $duplicate_first_revision->label());
  }

  /**
   * Tests that the translation status is changed when duplicating an entity.
   */
  public function testDuplicateEntityTranslationStatus() {
    // Create a test entity with some translations.
    $entity = EntityTestMul::create([
      'name' => $this->randomString(),
      'language' => 'en',
    ]);
    $entity->save();
    $entity->addTranslation('de');
    $entity->addTranslation('nl');
    $entity->save();

    // Verify that a removed translation is not affected.
    $entity->removeTranslation('de');

    $duplicate = $entity->createDuplicate();

    $this->assertSame($duplicate->getTranslationStatus('en'), TranslationStatusInterface::TRANSLATION_CREATED, 'Language en has correct translation status after cloning.');
    $this->assertSame($duplicate->getTranslationStatus('nl'), TranslationStatusInterface::TRANSLATION_CREATED, 'Language nl has correct translation status after cloning.');
    $this->assertSame($duplicate->getTranslationStatus('de'), TranslationStatusInterface::TRANSLATION_REMOVED, 'Language de has correct translation status after cloning.');
  }

}
