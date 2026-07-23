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

  /**
   * Method appendInterventionServiceEntry.
   *
   * A raw DBAL statement — not the ORM's persist()/flush() — is used
   * deliberately: a unique-constraint violation during an ORM flush() closes
   * the EntityManager (see
   * `Automation\Infrastructure\Persistence\Doctrine\Repository\AutomationRunRepository::reserveRun()`
   * and `Intervention\Infrastructure\Adapter\Recurrence\DoctrineInterventionRecurrenceAdapter::reserveRun()`
   * for the same concern and precedent). A duplicate dedup key is an
   * expected, routine outcome here (at-least-once event redelivery, or a
   * later publication re-reading an already-applied change), not an
   * exceptional one.
   *
   * @since 1.0.0
   */
  public function appendInterventionServiceEntry(EquipmentMaintenanceLog $entry, string $dedupKey): void
  {
    // ON CONFLICT DO NOTHING makes a duplicate dedup_key an in-DB no-op: no
    // exception is raised and the surrounding transaction is never aborted
    // (catching the violation instead poisons it on PostgreSQL).
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO equipment_maintenance_logs '
      . '(id, equipment_id, organization_id, started_at, completed_at, source, intervention_id, intervention_number, work_item_action, actor_id, summary, dedup_key) '
      . 'VALUES (:id, :equipmentId, :organizationId, :startedAt, :completedAt, :source, :interventionId, :interventionNumber, :workItemAction, :actorId, :summary, :dedupKey) '
      . 'ON CONFLICT DO NOTHING',
      [
        'id' => (string) $entry->id(),
        'equipmentId' => (string) $entry->equipmentId(),
        'organizationId' => (string) $entry->organizationId(),
        'startedAt' => $entry->startedAt(),
        'completedAt' => $entry->completedAt(),
        'source' => $entry->source()->value,
        'interventionId' => $entry->interventionId(),
        'interventionNumber' => $entry->interventionNumber(),
        'workItemAction' => $entry->workItemAction(),
        'actorId' => $entry->actorId(),
        'summary' => $entry->summary(),
        'dedupKey' => $dedupKey,
      ],
      [
        'startedAt' => 'datetime_immutable',
        'completedAt' => 'datetime_immutable',
      ],
    );
  }
  // #endregion
}
