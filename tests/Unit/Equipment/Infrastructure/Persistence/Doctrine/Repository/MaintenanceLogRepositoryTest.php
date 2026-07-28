<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Domain\ValueObject\EquipmentId;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentMaintenanceLogRecord, EquipmentRecord};
use Equipment\Infrastructure\Persistence\Doctrine\Repository\MaintenanceLogRepository;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceLogRepositoryTest.
 *
 * @category Repository Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceLogRepository::class)]
final class MaintenanceLogRepositoryTest extends TestCase
{
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655449001';

  // #region Methods
  #[Test]
  public function testFindOpenByEquipmentIdReturnsNullWhenNoLogIsStillOpen(): void
  {
    $equipmentId = EquipmentId::fromString(self::EQUIPMENT_ID);
    $equipmentReference = new EquipmentRecord();

    $doctrineRepository = $this->createMock(EntityRepository::class);
    $doctrineRepository->expects(self::once())
      ->method('findOneBy')
      ->with(
        ['equipment' => $equipmentReference, 'completedAt' => null],
        ['startedAt' => 'DESC'],
      )
      ->willReturn(null);

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->expects(self::once())
      ->method('getRepository')
      ->with(EquipmentMaintenanceLogRecord::class)
      ->willReturn($doctrineRepository);
    $entityManager->expects(self::once())
      ->method('getReference')
      ->with(EquipmentRecord::class, self::EQUIPMENT_ID)
      ->willReturn($equipmentReference);

    $repository = new MaintenanceLogRepository(entityManager: $entityManager);

    self::assertNull($repository->findOpenByEquipmentId($equipmentId));
  }
  // #endregion
}
