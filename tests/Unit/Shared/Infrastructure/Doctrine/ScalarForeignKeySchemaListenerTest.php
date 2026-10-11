<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Infrastructure\Doctrine\ScalarForeignKeySchemaListener;

/**
 * Class ScalarForeignKeySchemaListenerTest
 *
 * Protects foreign-key deletion actions and database isolation after ORM decoupling.
 *
 * @category Doctrine Tests
 */
#[CoversClass(ScalarForeignKeySchemaListener::class)]
final class ScalarForeignKeySchemaListenerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function organizationKeysPreserveCascadeDeletionAndAreIdempotent(): void
  {
    $schema = new Schema();
    $schema->createTable('organizations')->addColumn('id', 'string');
    foreach (['equipment', 'equipment_tag_catalog', 'facility_metadata_fields', 'facilities', 'checklists', 'inspections', 'inspection_responses', 'intervention_labels', 'interventions', 'intervention_recurrences', 'intervention_templates', 'maintenance_schedules', 'messaging_conversations'] as $name) {
      $schema->createTable($name)->addColumn('organization_id', 'string');
    }

    $this->listen($schema);
    $this->listen($schema);

    foreach ($schema->getTables() as $table) {
      if ('organizations' === $table->getName()) {
        continue;
      }

      self::assertCount(1, $table->getForeignKeys());
      foreach ($table->getForeignKeys() as $key) {
        self::assertSame(['organization_id'], $key->getLocalColumns());
        self::assertSame('organizations', $key->getForeignTableName());
        self::assertSame(['id'], $key->getForeignColumns());
        self::assertSame('CASCADE', $key->onDelete());
        self::assertFalse($key->getOption('deferrable'));
      }
    }
  }

  #[Test]
  public function roleLinksKeepNoActionAndTheirExistingConstraintNames(): void
  {
    $schema = new Schema();
    $schema->createTable('users')->addColumn('id', 'string');
    $schema->createTable('roles')->addColumn('id', 'string');
    $links = $schema->createTable('user_roles');
    $links->addColumn('user_id', 'string');
    $links->addColumn('role_id', 'string');
    $links->setPrimaryKey(['user_id', 'role_id']);

    $this->listen($schema);
    $this->listen($schema);

    self::assertCount(2, $links->getForeignKeys());
    $userKey = $links->getForeignKey('FK_54FCD59FA76ED395');
    $roleKey = $links->getForeignKey('FK_54FCD59FD60322AC');
    self::assertSame(['user_id'], $userKey->getLocalColumns());
    self::assertSame(['role_id'], $roleKey->getLocalColumns());
    self::assertNull($userKey->onDelete());
    self::assertNull($roleKey->onDelete());
    self::assertSame(['user_id', 'role_id'], $links->getPrimaryKey()?->getColumns());
  }

  #[Test]
  public function neverAddsKeysToATableWhoseOwnerIsOutsideTheSchema(): void
  {
    $schema = new Schema();
    $equipment = $schema->createTable('equipment');
    $equipment->addColumn('organization_id', 'string');
    $links = $schema->createTable('user_roles');
    $links->addColumn('user_id', 'string');
    $links->addColumn('role_id', 'string');

    $this->listen($schema);

    self::assertSame([], $equipment->getForeignKeys());
    self::assertSame([], $links->getForeignKeys());
    self::assertFalse($schema->hasTable('organizations'));
    self::assertFalse($schema->hasTable('users'));
  }

  private function listen(Schema $schema): void
  {
    new ScalarForeignKeySchemaListener()->postGenerateSchema(
      new GenerateSchemaEventArgs($this->createStub(EntityManagerInterface::class), $schema),
    );
  }
  // #endregion
}
