<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Infrastructure\Adapter\Equipment\EquipmentMaintenanceDueStatusAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test EquipmentMaintenanceDueStatusAdapterTest.
 *
 * Only the trivial empty-ids early return is exercised here: a mocked
 * EntityManager/QueryBuilder never parses real DQL, so the actual query
 * (organization scoping, the `IN (:equipmentIds)` predicate, the
 * `unscheduled` default for unmatched ids) is covered by the real-database
 * integration test instead — see
 * `Tests\Integration\Maintenance\Infrastructure\Adapter\Equipment\EquipmentMaintenanceDueStatusAdapterTest`.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(EquipmentMaintenanceDueStatusAdapter::class)]
final class EquipmentMaintenanceDueStatusAdapterTest extends TestCase
{
  #[Test]
  public function testDueStatusesForEquipmentReturnsEmptyArrayWithoutQueryingWhenNoIdsGiven(): void
  {
    /** @var EntityManagerInterface&MockObject $entityManager */
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::never())->method('createQueryBuilder');
    $entityManager->expects(self::never())->method('getReference');

    $adapter = new EquipmentMaintenanceDueStatusAdapter($entityManager);

    self::assertSame(
      [],
      $adapter->dueStatusesForEquipment('550e8400-e29b-41d4-a716-446655449001', []),
    );
  }
}
