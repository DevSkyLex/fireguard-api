<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\MaintenanceLogRepositoryPort;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\MaintenanceLogMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentMaintenanceLogRecord, EquipmentRecord};

use function array_map;

/**
 * Repository MaintenanceLogRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceLogRepository implements MaintenanceLogRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<EquipmentMaintenanceLogRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(EquipmentMaintenanceLogRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(EquipmentMaintenanceLog $log): void
  {
    $existing = $this->repository->find((string) $log->id());

    if ($existing instanceof EquipmentMaintenanceLogRecord) {
      $existing->completedAt = $log->completedAt();
      $existing->startedAt = $log->startedAt();
    } else {
      $record = MaintenanceLogMapper::toRecord($log);
      /** @var EquipmentRecord $equipment */
      $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $log->equipmentId());
      $record->equipment = $equipment;
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findOpenByEquipmentId.
   *
   * @since 1.0.0
   */
  public function findOpenByEquipmentId(EquipmentId $equipmentId): ?EquipmentMaintenanceLog
  {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    $record = $this->repository->findOneBy(
      ['equipment' => $equipment, 'completedAt' => null],
      ['startedAt' => 'DESC'],
    );

    if (!$record instanceof EquipmentMaintenanceLogRecord) {
      return null;
    }

    return MaintenanceLogMapper::toDomain($record);
  }

  /**
   * Method findByEquipmentId.
   *
   * @since 1.0.0
   */
  public function findByEquipmentId(
    EquipmentOrganizationId $organizationId,
    EquipmentId $equipmentId,
    int $limit = 20,
    int $offset = 0,
  ): array {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('m')
      ->from(EquipmentMaintenanceLogRecord::class, 'm')
      ->where('m.equipment = :equipment')
      ->andWhere('m.organizationId = :organizationId')
      ->setParameter('equipment', $equipment)
      ->setParameter('organizationId', (string) $organizationId)
      ->orderBy('m.startedAt', 'DESC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<EquipmentMaintenanceLogRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map(
      static fn (EquipmentMaintenanceLogRecord $record): EquipmentMaintenanceLog => MaintenanceLogMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByEquipmentId.
   *
   * @since 1.0.0
   */
  public function countByEquipmentId(EquipmentOrganizationId $organizationId, EquipmentId $equipmentId): int
  {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    $qb = $this->entityManager->createQueryBuilder();
    $qb
      ->select('COUNT(m.id)')
      ->from(EquipmentMaintenanceLogRecord::class, 'm')
      ->where('m.equipment = :equipment')
      ->andWhere('m.organizationId = :organizationId')
      ->setParameter('equipment', $equipment)
      ->setParameter('organizationId', (string) $organizationId);

    return (int) $qb->getQuery()->getSingleScalarResult();
  }
  // #endregion
}
