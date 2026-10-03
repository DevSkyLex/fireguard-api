<?php

declare(strict_types=1);

namespace Tests\Integration\Facility\Infrastructure\Adapter\Hierarchy;

use Doctrine\DBAL\{DriverManager, Exception};
use Doctrine\ORM\EntityManagerInterface;
use Facility\Infrastructure\Adapter\Hierarchy\FacilityHierarchySnapshotAdapter;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

use function array_intersect_key;
use function str_contains;

/**
 * Test FacilityHierarchySnapshotAdapterTest.
 *
 * @category Integration Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class FacilityHierarchySnapshotAdapterTest extends KernelTestCase
{
  // #region Tests
  /**
   * Method testOrganizationLocksSerializeIndependentPostgresConnections.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testOrganizationLocksSerializeIndependentPostgresConnections(): void
  {
    self::bootKernel();
    $em = self::getContainer()->get('doctrine.orm.main_entity_manager');
    self::assertInstanceOf(EntityManagerInterface::class, $em);
    $primary = $em->getConnection();
    $params = $primary->getParams();
    self::assertTrue(str_contains((string) ($params['dbname'] ?? ''), '_w'), 'Independent writes must remain inside the isolated test clone.');
    $params = array_intersect_key($params, ['host' => true, 'port' => true, 'dbname' => true, 'user' => true, 'password' => true, 'charset' => true]);
    $params['driver'] = 'pdo_pgsql';
    $other = DriverManager::getConnection($params);
    self::assertNotSame($primary->fetchOne('SELECT pg_backend_pid()'), $other->fetchOne('SELECT pg_backend_pid()'));
    $organization = 'f6200000-0000-4000-8000-000000000001';
    $primary->beginTransaction();
    new FacilityHierarchySnapshotAdapter($em)->lock($organization);
    $other->beginTransaction();

    try {
      $other->executeStatement("SET LOCAL lock_timeout = '75ms'");
      // A different organization remains usable while this one is locked.
      $other->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:scope, 0))', ['scope' => 'facility-hierarchy:other'])->free();

      try {
        $other->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:scope, 0))', ['scope' => 'facility-hierarchy:' . $organization])->free();
        self::fail('A second relationship writer must wait for the organization transaction.');
      } catch (Exception $exception) {
        self::assertStringContainsString('lock timeout', $exception->getMessage());
      }
    } finally {
      $other->rollBack();
      $other->close();
      $primary->rollBack();
    }
  }

  /**
   * Method testLocksCannotBeAcquiredOutsideTheOwningTransaction.
   *
   * @since 1.0.0
   */
  #[Test]
  public function testLocksCannotBeAcquiredOutsideTheOwningTransaction(): void
  {
    $connection = $this->createStub(\Doctrine\DBAL\Connection::class);
    $connection->method('isTransactionActive')->willReturn(false);
    $em = $this->createStub(EntityManagerInterface::class);
    $em->method('getConnection')->willReturn($connection);
    $this->expectException(LogicException::class);
    new FacilityHierarchySnapshotAdapter($em)->lock('organization');
  }
  // #endregion
}
