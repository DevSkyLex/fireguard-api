<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\{EntityManagerInterface, QueryBuilder};
use Maintenance\Application\Contract\Export\MaintenanceScheduleExportCandidate;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function implode;
use function max;
use function min;

/**
 * Repository MaintenanceScheduleRepository.
 *
 * @category Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleRepository implements MaintenanceScheduleRepositoryPort
{
  // #region Constants
  /**
   * Constant DATABASE_TIMESTAMP_FORMAT
   *
   * Preserves the existing database timestamp serialization for every snapshot field.
   */
  private const string DATABASE_TIMESTAMP_FORMAT = 'Y-m-d H:i:s';

  /**
   * Constant COUNT_PROJECTION
   *
   * Uses the same scalar DQL projection for filtered schedule counts.
   */
  private const string COUNT_PROJECTION = 'COUNT(s.id)';

  /**
   * Constant ORGANIZATION_PREDICATE
   *
   * Reusable DQL clause that scopes schedules to an organization.
   *
   * @access private
   *
   * @var string
   */
  private const string ORGANIZATION_PREDICATE = 's.organization = :organization';

  /**
   * Constant FACILITY_PREDICATE
   *
   * @access private
   *
   * @var string
   */
  private const string FACILITY_PREDICATE = 's.facilityId = :facilityId';

  /**
   * Constant EQUIPMENT_TYPE_PREDICATE
   *
   * @access private
   *
   * @var string
   */
  private const string EQUIPMENT_TYPE_PREDICATE = 's.equipmentType = :equipmentType';

  /**
   * Constant DUE_BEFORE_PREDICATE
   *
   * Excludes schedules without a next due date from the upper-bound filter.
   *
   * @access private
   *
   * @var string
   */
  private const string DUE_BEFORE_PREDICATE = 's.nextDueAt IS NOT NULL AND s.nextDueAt <= :dueBefore';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method findById.
   *
   * Refreshes a found row before mapping its current persisted state.
   *
   * @access public
   *
   * @param string $id the maintenance schedule identifier
   *
   * @return ?MaintenanceScheduleView the schedule view, or null when not found
   */
  public function findById(string $id): ?MaintenanceScheduleView
  {
    /** @var array{id: string, organization_id: string, equipment_id: string, facility_id: ?string, equipment_type: string, interval_override: ?string, last_inspection_closed_at: ?string, next_due_at: ?string, due_status: string, last_reminded_at: ?string, reminded_for: ?string, created_at: string, updated_at: string, evaluated_at: ?string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM maintenance_schedules WHERE id = :id', ['id' => $id]);

    return false === $row ? null : $this->scalarView($row);
  }

  /**
   * Method findByOrganizationAndEquipment.
   *
   * Looks up a schedule within the specified organization and equipment scope.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return ?MaintenanceScheduleView the schedule view, or null when not found
   */
  public function findByOrganizationAndEquipment(string $organizationId, string $equipmentId): ?MaintenanceScheduleView
  {
    /** @var array{id: string, organization_id: string, equipment_id: string, facility_id: ?string, equipment_type: string, interval_override: ?string, last_inspection_closed_at: ?string, next_due_at: ?string, due_status: string, last_reminded_at: ?string, reminded_for: ?string, created_at: string, updated_at: string, evaluated_at: ?string}|false $row */
    $row = $this->entityManager->getConnection()->fetchAssociative('SELECT * FROM maintenance_schedules WHERE organization_id = :organization AND equipment_id = :equipment', ['organization' => $organizationId, 'equipment' => $equipmentId]);

    return false === $row ? null : $this->scalarView($row);
  }

  /**
   * @param list<string> $equipmentIds
   *
   * @return array<string, MaintenanceScheduleView>
   */
  public function findForEquipment(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<array{id: string, organization_id: string, equipment_id: string, facility_id: ?string, equipment_type: string, interval_override: ?string, last_inspection_closed_at: ?string, next_due_at: ?string, due_status: string, last_reminded_at: ?string, reminded_for: ?string, created_at: string, updated_at: string, evaluated_at: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative(
      'SELECT * FROM maintenance_schedules WHERE organization_id = :organization AND equipment_id IN (:equipment)',
      ['organization' => $organizationId, 'equipment' => $equipmentIds],
      ['equipment' => ArrayParameterType::STRING],
    );
    $schedules = [];
    foreach ($rows as $row) {
      $view = $this->scalarView($row);
      $schedules[$view->equipmentId] = $view;
    }

    return $schedules;
  }

  /**
   * @param list<MaintenanceScheduleSnapshot> $snapshots bounded, locked schedule batch
   */
  public function saveBatch(array $snapshots): void
  {
    if ([] === $snapshots) {
      return;
    }
    $values = [];
    $parameters = [];
    $now = new DateTimeImmutable()->format(self::DATABASE_TIMESTAMP_FORMAT);
    foreach ($snapshots as $index => $snapshot) {
      $row = ['id' => $snapshot->id ?? $this->uuidFactory->generateRaw(), 'organization' => $snapshot->organizationId, 'equipment' => $snapshot->equipmentId,
        'facility' => $snapshot->facilityId, 'type' => $snapshot->equipmentType, 'override' => $snapshot->intervalOverride,
        'closed' => $snapshot->lastInspectionClosedAt?->format(self::DATABASE_TIMESTAMP_FORMAT), 'due' => $snapshot->nextDueAt?->format(self::DATABASE_TIMESTAMP_FORMAT), 'status' => $snapshot->dueStatus,
        'reminded' => $snapshot->lastRemindedAt?->format(self::DATABASE_TIMESTAMP_FORMAT), 'remindedFor' => $snapshot->remindedFor?->format(self::DATABASE_TIMESTAMP_FORMAT),
        'created' => $now, 'updated' => $now, 'evaluated' => $snapshot->evaluatedAt?->format(self::DATABASE_TIMESTAMP_FORMAT)];
      $placeholders = [];
      foreach ($row as $name => $value) {
        $key = 'r' . $index . $name;
        $placeholders[] = ':' . $key;
        $parameters[$key] = $value;
      }
      $values[] = '(' . implode(', ', $placeholders) . ')';
    }
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO maintenance_schedules (id, organization_id, equipment_id, facility_id, equipment_type, interval_override,
        last_inspection_closed_at, next_due_at, due_status, last_reminded_at, reminded_for, created_at, updated_at, evaluated_at) VALUES ' . implode(', ', $values) . '
       ON CONFLICT (organization_id, equipment_id) DO UPDATE SET facility_id = EXCLUDED.facility_id, equipment_type = EXCLUDED.equipment_type,
        interval_override = EXCLUDED.interval_override, last_inspection_closed_at = EXCLUDED.last_inspection_closed_at,
        next_due_at = EXCLUDED.next_due_at, due_status = EXCLUDED.due_status, last_reminded_at = EXCLUDED.last_reminded_at,
        reminded_for = EXCLUDED.reminded_for, updated_at = EXCLUDED.updated_at, evaluated_at = EXCLUDED.evaluated_at',
      $parameters,
    );
  }

  /**
   * Method list.
   *
   * Returns a bounded page ordered by due date, with undated schedules last.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   * @param ?DateTimeImmutable $dueBefore optional upper bound on the next due date
   * @param int $page the page number
   * @param int $itemsPerPage the maximum number of results per page, capped at 100
   *
   * @return MaintenanceSchedulePage the schedule page result
   */
  public function list(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
    ?DateTimeImmutable $dueBefore,
    int $page,
    int $itemsPerPage,
  ): MaintenanceSchedulePage {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('s')
      ->from(MaintenanceScheduleRecord::class, 's')
      ->where(self::ORGANIZATION_PREDICATE)
      ->setParameter('organization', $organization);

    if (null !== $facilityId) {
      $qb->andWhere(self::FACILITY_PREDICATE)->setParameter('facilityId', $facilityId);
    }
    if (null !== $equipmentType) {
      $qb->andWhere(self::EQUIPMENT_TYPE_PREDICATE)->setParameter('equipmentType', $equipmentType);
    }
    if (null !== $dueStatus) {
      $qb->andWhere('s.dueStatus = :dueStatus')->setParameter('dueStatus', $dueStatus);
    }
    if (null !== $dueBefore) {
      $qb->andWhere(self::DUE_BEFORE_PREDICATE)->setParameter('dueBefore', $dueBefore);
    }

    $total = (int) (clone $qb)
      ->select(self::COUNT_PROJECTION)
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb
      ->orderBy('CASE WHEN s.nextDueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
      ->addOrderBy('s.nextDueAt', 'ASC')
      ->addOrderBy('s.id', 'ASC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MaintenanceSchedulePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  /**
   * Method listDueForCampaign.
   *
   * Selects due-soon and overdue schedules matching campaign filters.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param DateTimeImmutable $dueBefore the upper bound on the next due date
   *
   * @return list<MaintenanceScheduleView> the matching schedules
   */
  public function listDueForCampaign(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    DateTimeImmutable $dueBefore,
    int $limit = 201,
  ): array {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('s')
      ->from(MaintenanceScheduleRecord::class, 's')
      ->where(self::ORGANIZATION_PREDICATE)
      ->andWhere('s.dueStatus IN (:dueStatuses)')
      ->andWhere(self::DUE_BEFORE_PREDICATE)
      ->setParameter('organization', $organization)
      ->setParameter('dueStatuses', ['due_soon', 'overdue'])
      ->setParameter('dueBefore', $dueBefore)
      ->orderBy('s.nextDueAt', 'ASC')
      ->addOrderBy('s.id', 'ASC')
      ->setMaxResults(max(1, $limit));

    if (null !== $facilityId) {
      $qb->andWhere(self::FACILITY_PREDICATE)->setParameter('facilityId', $facilityId);
    }
    if (null !== $equipmentType) {
      $qb->andWhere(self::EQUIPMENT_TYPE_PREDICATE)->setParameter('equipmentType', $equipmentType);
    }

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map($this->view(...), $records);
  }

  /**
   * Counts campaign matches with the same predicates as the bounded read.
   */
  public function countDueForCampaign(string $organizationId, ?string $facilityId, ?string $equipmentType, DateTimeImmutable $dueBefore): int
  {
    $qb = $this->exportQuery($organizationId, $facilityId, $equipmentType, null, $dueBefore)
      ->andWhere('s.dueStatus IN (:dueStatuses)')->setParameter('dueStatuses', ['due_soon', 'overdue']);

    return (int) $qb->select(self::COUNT_PROJECTION)->getQuery()->getSingleScalarResult();
  }

  /**
   * Method pageForSweep.
   *
   * Returns a bounded, id-ordered page spanning all organizations for the recompute sweep.
   *
   * @access public
   *
   * @param int $limit requested page size, clamped to at least one
   * @param int $offset result offset, clamped to zero or greater
   *
   * @return MaintenanceSchedulePage the schedule page result
   */
  public function pageForSweep(int $limit, int $offset): MaintenanceSchedulePage
  {
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    /** @var list<array{id: string, organization_id: string, equipment_id: string, facility_id: ?string, equipment_type: string, interval_override: ?string, last_inspection_closed_at: ?string, next_due_at: ?string, due_status: string, last_reminded_at: ?string, reminded_for: ?string, created_at: string, updated_at: string, evaluated_at: ?string}> $rows */
    $rows = $this->entityManager->getConnection()->fetchAllAssociative('SELECT * FROM maintenance_schedules ORDER BY id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);

    return new MaintenanceSchedulePage(array_map($this->scalarView(...), $rows), 0, $limit, 0);
  }

  /**
   * Method save.
   *
   * Creates or updates a schedule from a complete snapshot and returns its view.
   *
   * @access public
   *
   * @param MaintenanceScheduleSnapshot $snapshot the schedule snapshot
   *
   * @return MaintenanceScheduleView the persisted schedule view
   *
   * @throws MaintenanceNotFoundException when an explicitly identified schedule is missing
   */
  public function save(MaintenanceScheduleSnapshot $snapshot): MaintenanceScheduleView
  {
    $existing = null !== $snapshot->id ? $this->findById($snapshot->id) : $this->findByOrganizationAndEquipment($snapshot->organizationId, $snapshot->equipmentId);
    if (null !== $snapshot->id && null === $existing) {
      throw MaintenanceNotFoundException::withId($snapshot->id);
    }
    $id = $existing->id ?? $this->uuidFactory->generateRaw();
    $now = new DateTimeImmutable();
    $this->entityManager->getConnection()->executeStatement(
      'INSERT INTO maintenance_schedules (id, organization_id, equipment_id, facility_id, equipment_type, interval_override,
        last_inspection_closed_at, next_due_at, due_status, last_reminded_at, reminded_for, created_at, updated_at, evaluated_at)
       VALUES (:id, :organization, :equipment, :facility, :type, :override, :closed, :due, :status, :reminded, :remindedFor, :created, :updated, :evaluated)
       ON CONFLICT (organization_id, equipment_id) DO UPDATE SET facility_id = EXCLUDED.facility_id, equipment_type = EXCLUDED.equipment_type,
        interval_override = EXCLUDED.interval_override, last_inspection_closed_at = EXCLUDED.last_inspection_closed_at,
        next_due_at = EXCLUDED.next_due_at, due_status = EXCLUDED.due_status, last_reminded_at = EXCLUDED.last_reminded_at,
        reminded_for = EXCLUDED.reminded_for, updated_at = EXCLUDED.updated_at, evaluated_at = EXCLUDED.evaluated_at',
      ['id' => $id, 'organization' => $snapshot->organizationId, 'equipment' => $snapshot->equipmentId, 'facility' => $snapshot->facilityId,
        'type' => $snapshot->equipmentType, 'override' => $snapshot->intervalOverride, 'closed' => $snapshot->lastInspectionClosedAt?->format(self::DATABASE_TIMESTAMP_FORMAT),
        'due' => $snapshot->nextDueAt?->format(self::DATABASE_TIMESTAMP_FORMAT), 'status' => $snapshot->dueStatus,
        'reminded' => $snapshot->lastRemindedAt?->format(self::DATABASE_TIMESTAMP_FORMAT), 'remindedFor' => $snapshot->remindedFor?->format(self::DATABASE_TIMESTAMP_FORMAT),
        'created' => ($existing->createdAt ?? $now)->format(self::DATABASE_TIMESTAMP_FORMAT), 'updated' => $now->format(self::DATABASE_TIMESTAMP_FORMAT), 'evaluated' => $snapshot->evaluatedAt?->format(self::DATABASE_TIMESTAMP_FORMAT)],
    );

    return $this->findByOrganizationAndEquipment($snapshot->organizationId, $snapshot->equipmentId) ?? throw MaintenanceNotFoundException::withId($id);
  }

  /**
   * Method removeByOrganizationAndEquipment.
   *
   * Removes a tracked schedule when equipment is no longer eligible.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return void
   */
  public function removeByOrganizationAndEquipment(string $organizationId, string $equipmentId): void
  {
    $this->entityManager->getConnection()->executeStatement('DELETE FROM maintenance_schedules WHERE organization_id = :organization AND equipment_id = :equipment', ['organization' => $organizationId, 'equipment' => $equipmentId]);
  }

  /**
   * Method countForExport.
   *
   * Counts filtered rows without fetching them, allowing callers to enforce the export cap.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   * @param ?DateTimeImmutable $dueBefore optional upper bound on the next due date
   *
   * @return int the matching schedule count
   */
  public function countForExport(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
    ?DateTimeImmutable $dueBefore = null,
  ): int {
    $qb = $this->exportQuery($organizationId, $facilityId, $equipmentType, $dueStatus, $dueBefore);

    return (int) $qb->select(self::COUNT_PROJECTION)->getQuery()->getSingleScalarResult();
  }

  /**
   * Method listExportCandidates.
   *
   * Returns the stable export ordering; callers should check the row cap first.
   *
   * @access public
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   * @param ?DateTimeImmutable $dueBefore optional upper bound on the next due date
   *
   * @return list<MaintenanceScheduleExportCandidate> the matching schedule rows
   */
  public function listExportCandidates(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
    ?DateTimeImmutable $dueBefore = null,
  ): array {
    $qb = $this->exportQuery($organizationId, $facilityId, $equipmentType, $dueStatus, $dueBefore)
      ->select('s')
      ->orderBy('s.updatedAt', 'DESC')
      ->addOrderBy('s.id', 'ASC');

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map($this->exportCandidate(...), $records);
  }

  /**
   * Method exportQuery.
   *
   * Builds the base, unpaginated query shared by {@see self::countForExport()}
   * and {@see self::listExportCandidates()} — the same (cheap, indexed)
   * filters applied by {@see self::list()}, including `dueBefore`.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param ?string $facilityId optional facility filter
   * @param ?string $equipmentType optional equipment type filter
   * @param ?string $dueStatus optional due status filter
   *
   * @return QueryBuilder the query builder result
   */
  private function exportQuery(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    ?string $dueStatus,
    ?DateTimeImmutable $dueBefore = null,
  ): QueryBuilder {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->from(MaintenanceScheduleRecord::class, 's')
      ->where(self::ORGANIZATION_PREDICATE)
      ->setParameter('organization', $organization);

    if (null !== $facilityId) {
      $qb->andWhere(self::FACILITY_PREDICATE)->setParameter('facilityId', $facilityId);
    }
    if (null !== $equipmentType) {
      $qb->andWhere(self::EQUIPMENT_TYPE_PREDICATE)->setParameter('equipmentType', $equipmentType);
    }
    if (null !== $dueStatus) {
      $qb->andWhere('s.dueStatus = :dueStatus')->setParameter('dueStatus', $dueStatus);
    }

    if (null !== $dueBefore) {
      $qb->andWhere(self::DUE_BEFORE_PREDICATE)->setParameter('dueBefore', $dueBefore);
    }

    return $qb;
  }

  /**
   * Method exportCandidate.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRecord $record the record value
   *
   * @return MaintenanceScheduleExportCandidate the export candidate result
   */
  private function exportCandidate(MaintenanceScheduleRecord $record): MaintenanceScheduleExportCandidate
  {
    return new MaintenanceScheduleExportCandidate(
      id: $record->id,
      equipmentId: $record->equipmentId,
      equipmentType: $record->equipmentType,
      facilityId: $record->facilityId,
      intervalOverride: $record->intervalOverride,
      lastInspectionClosedAt: $record->lastInspectionClosedAt?->format(DateTimeInterface::ATOM),
      nextDueAt: $record->nextDueAt?->format(DateTimeInterface::ATOM),
      dueStatus: $record->dueStatus,
      createdAt: $record->createdAt->format(DateTimeInterface::ATOM),
      updatedAt: $record->updatedAt->format(DateTimeInterface::ATOM),
    );
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRecord $record the record value
   *
   * @return MaintenanceScheduleView the schedule view result
   */
  private function view(MaintenanceScheduleRecord $record): MaintenanceScheduleView
  {
    return new MaintenanceScheduleView(
      $record->id,
      $this->organizationId($record),
      $record->equipmentId,
      $record->facilityId,
      $record->equipmentType,
      $record->intervalOverride,
      $record->lastInspectionClosedAt,
      $record->nextDueAt,
      $record->dueStatus,
      $record->lastRemindedAt,
      $record->remindedFor,
      $record->createdAt,
      $record->updatedAt,
      $record->evaluatedAt,
    );
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRecord $record the record value
   *
   * @return string the organization id result
   */
  private function organizationId(MaintenanceScheduleRecord $record): string
  {
    if (!$record->organization instanceof OrganizationRecord) {
      throw MaintenanceNotFoundException::withId($record->id);
    }

    return $record->organization->id;
  }

  /**
   * Maps a detached scalar row without retaining a Doctrine record during worker sweeps.
   *
   * @param array{id: string, organization_id: string, equipment_id: string, facility_id: ?string, equipment_type: string, interval_override: ?string, last_inspection_closed_at: ?string, next_due_at: ?string, due_status: string, last_reminded_at: ?string, reminded_for: ?string, created_at: string, updated_at: string, evaluated_at: ?string} $row persisted scalar row
   *
   * @return MaintenanceScheduleView current schedule
   */
  private function scalarView(array $row): MaintenanceScheduleView
  {
    $date = static fn (?string $value): ?DateTimeImmutable => null === $value ? null : new DateTimeImmutable($value);

    return new MaintenanceScheduleView(
      (string) $row['id'],
      (string) $row['organization_id'],
      (string) $row['equipment_id'],
      $row['facility_id'],
      (string) $row['equipment_type'],
      $row['interval_override'],
      $date($row['last_inspection_closed_at']),
      $date($row['next_due_at']),
      (string) $row['due_status'],
      $date($row['last_reminded_at']),
      $date($row['reminded_for']),
      new DateTimeImmutable((string) $row['created_at']),
      new DateTimeImmutable((string) $row['updated_at']),
      $date($row['evaluated_at']),
    );
  }
  // #endregion
}
