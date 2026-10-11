<?php

declare(strict_types=1);

namespace Tests\Integration\Shared\Infrastructure\Doctrine;

use Authorization\Infrastructure\Persistence\Doctrine\Record\UserRoleRecord;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\{Configuration, DriverManager};
use Doctrine\DBAL\Schema\DefaultSchemaManagerFactory;
use Doctrine\ORM\{EntityManager, EntityManagerInterface};
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\Test;
use Shared\Infrastructure\Doctrine\{PartialIndexSchemaListener, ScalarForeignKeySchemaListener};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function explode;

/**
 * Class ModuleRecordBoundaryTest
 *
 * Checks the actual manager mappings and PostgreSQL constraints rather than source imports.
 *
 * @category Doctrine Tests
 */
final class ModuleRecordBoundaryTest extends KernelTestCase
{
  // #region Methods
  #[Test]
  public function bothManagersKeepModuleAssociationsLocalAndScalarForeignKeysInSync(): void
  {
    self::bootKernel();

    foreach (['auth', 'main'] as $managerName) {
      /** @var EntityManagerInterface $manager */
      $manager = self::getContainer()->get('doctrine.orm.' . $managerName . '_entity_manager');
      $metadata = $manager->getMetadataFactory()->getAllMetadata();
      foreach ($metadata as $record) {
        $module = explode('\\', $record->name)[0];
        foreach ($record->associationMappings as $association) {
          self::assertSame($module, explode('\\', $association->targetEntity)[0], $record->name . ' references a foreign module Record');
        }
      }

      // Schema introspection owns a separate connection; DAMA's surrounding test
      // transaction must not hide a constraint or inherit an earlier SQL failure.
      $configuration = new Configuration();
      $configuration->setSchemaManagerFactory(new DefaultSchemaManagerFactory());
      $connection = DriverManager::getConnection($manager->getConnection()->getParams(), $configuration);
      $events = new EventManager();
      $hasScalarKeys = false;
      $hasPartialIndexes = false;
      foreach ($manager->getEventManager()->getListeners('postGenerateSchema') as $listener) {
        if ($listener instanceof ScalarForeignKeySchemaListener || $listener instanceof PartialIndexSchemaListener) {
          $events->addEventListener(['postGenerateSchema'], $listener);
          $hasScalarKeys = $hasScalarKeys || $listener instanceof ScalarForeignKeySchemaListener;
          $hasPartialIndexes = $hasPartialIndexes || $listener instanceof PartialIndexSchemaListener;
        }
      }
      self::assertTrue($hasScalarKeys, $managerName . ' must register its scalar foreign-key listener');
      self::assertSame('main' === $managerName, $hasPartialIndexes);
      // Framework transport listeners probe connection identity by writing a
      // temporary table. Only the registered Record-schema listeners belong here.
      $schemaManager = new EntityManager($connection, $manager->getConfiguration(), $events);

      try {
        $connection->executeStatement('SET statement_timeout = 10000');
        $schema = new SchemaTool($schemaManager)->getSchemaFromMetadata($metadata);
        if ('auth' === $managerName) {
          self::assertFalse($manager->getMetadataFactory()->isTransient(UserRoleRecord::class));
          $table = $schema->getTable('user_roles');
          self::assertSame(['user_id', 'role_id'], $table->getPrimaryKey()?->getColumns());
          foreach ($table->getForeignKeys() as $key) {
            self::assertNull($key->onDelete());
          }
        }

        foreach ($schema->getTables() as $table) {
          foreach ($table->getForeignKeys() as $key) {
            if ('organizations' !== $key->getForeignTableName() && 'user_roles' !== $table->getName()) {
              continue;
            }

            $action = $connection->fetchOne(
              'SELECT c.confdeltype FROM pg_constraint c JOIN pg_class t ON t.oid = c.conrelid JOIN pg_class target ON target.oid = c.confrelid JOIN pg_attribute column_name ON column_name.attrelid = t.oid AND column_name.attnum = ANY(c.conkey) WHERE t.relname = :table AND target.relname = :target AND column_name.attname = :column',
              ['table' => $table->getName(), 'target' => $key->getForeignTableName(), 'column' => $key->getLocalColumns()[0]],
            );
            self::assertSame('user_roles' === $table->getName() ? 'a' : 'c', $action, $table->getName() . ' loses its foreign key deletion guarantee');
          }
        }
      } finally {
        $connection->close();
      }
    }
  }
  // #endregion
}
