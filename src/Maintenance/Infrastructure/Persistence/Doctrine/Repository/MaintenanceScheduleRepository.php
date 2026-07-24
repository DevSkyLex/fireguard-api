<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Maintenance\Application\Contract\Schedule\{MaintenanceSchedulePage, MaintenanceScheduleSnapshot, MaintenanceScheduleView};
use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\MaintenanceNotFoundException;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Repository MaintenanceScheduleRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaintenanceScheduleRepository implements MaintenanceScheduleRepositoryPort
{
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
  public function findById(string $id): ?MaintenanceScheduleView
  {
    $record = $this->entityManager->find(MaintenanceScheduleRecord::class, $id);

    return $record instanceof MaintenanceScheduleRecord ? $this->view($record) : null;
  }

  public function findByOrganizationAndEquipment(string $organizationId, string $equipmentId): ?MaintenanceScheduleView
  {
    $record = $this->findRecordByOrganizationAndEquipment($organizationId, $equipmentId);

    return $record instanceof MaintenanceScheduleRecord ? $this->view($record) : null;
  }

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
      ->where('s.organization = :organization')
      ->setParameter('organization', $organization);

    if (null !== $facilityId) {
      $qb->andWhere('s.facilityId = :facilityId')->setParameter('facilityId', $facilityId);
    }
    if (null !== $equipmentType) {
      $qb->andWhere('s.equipmentType = :equipmentType')->setParameter('equipmentType', $equipmentType);
    }
    if (null !== $dueStatus) {
      $qb->andWhere('s.dueStatus = :dueStatus')->setParameter('dueStatus', $dueStatus);
    }
    if (null !== $dueBefore) {
      $qb->andWhere('s.nextDueAt IS NOT NULL AND s.nextDueAt <= :dueBefore')->setParameter('dueBefore', $dueBefore);
    }

    $total = (int) (clone $qb)
      ->select('COUNT(s.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb
      ->orderBy('CASE WHEN s.nextDueAt IS NULL THEN 1 ELSE 0 END', 'ASC')
      ->addOrderBy('s.nextDueAt', 'ASC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MaintenanceSchedulePage(array_map($this->view(...), $records), $page, $itemsPerPage, $total);
  }

  public function listDueForCampaign(
    string $organizationId,
    ?string $facilityId,
    ?string $equipmentType,
    DateTimeImmutable $dueBefore,
  ): array {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('s')
      ->from(MaintenanceScheduleRecord::class, 's')
      ->where('s.organization = :organization')
      ->andWhere('s.dueStatus IN (:dueStatuses)')
      ->andWhere('s.nextDueAt IS NOT NULL AND s.nextDueAt <= :dueBefore')
      ->setParameter('organization', $organization)
      ->setParameter('dueStatuses', ['due_soon', 'overdue'])
      ->setParameter('dueBefore', $dueBefore)
      ->orderBy('s.nextDueAt', 'ASC');

    if (null !== $facilityId) {
      $qb->andWhere('s.facilityId = :facilityId')->setParameter('facilityId', $facilityId);
    }
    if (null !== $equipmentType) {
      $qb->andWhere('s.equipmentType = :equipmentType')->setParameter('equipmentType', $equipmentType);
    }

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb->getQuery()->getResult();

    return array_map($this->view(...), $records);
  }

  public function pageForSweep(int $limit, int $offset): MaintenanceSchedulePage
  {
    $limit = max(1, $limit);
    $offset = max(0, $offset);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('s')
      ->from(MaintenanceScheduleRecord::class, 's')
      ->orderBy('s.id', 'ASC')
      ->setFirstResult($offset)
      ->setMaxResults($limit);

    /** @var list<MaintenanceScheduleRecord> $records */
    $records = $qb->getQuery()->getResult();

    return new MaintenanceSchedulePage(array_map($this->view(...), $records), 0, $limit, 0);
  }

  public function save(MaintenanceScheduleSnapshot $snapshot): MaintenanceScheduleView
  {
    $record = null !== $snapshot->id
      ? $this->entityManager->find(MaintenanceScheduleRecord::class, $snapshot->id)
      : $this->findRecordByOrganizationAndEquipment($snapshot->organizationId, $snapshot->equipmentId);

    if (null !== $snapshot->id && !$record instanceof MaintenanceScheduleRecord) {
      throw MaintenanceNotFoundException::withId($snapshot->id);
    }

    $now = new DateTimeImmutable();
    $isNew = !$record instanceof MaintenanceScheduleRecord;

    if ($isNew) {
      $record = new MaintenanceScheduleRecord();
      $record->id = $this->uuidFactory->generateRaw();
      /** @var OrganizationRecord $organization */
      $organization = $this->entityManager->getReference(OrganizationRecord::class, $snapshot->organizationId);
      $record->organization = $organization;
      $record->equipmentId = $snapshot->equipmentId;
      $record->createdAt = $now;
    }

    $record->facilityId = $snapshot->facilityId;
    $record->equipmentType = $snapshot->equipmentType;
    $record->intervalOverride = $snapshot->intervalOverride;
    $record->lastInspectionClosedAt = $snapshot->lastInspectionClosedAt;
    $record->nextDueAt = $snapshot->nextDueAt;
    $record->dueStatus = $snapshot->dueStatus;
    $record->lastRemindedAt = $snapshot->lastRemindedAt;
    $record->remindedFor = $snapshot->remindedFor;
    $record->updatedAt = $now;

    if ($isNew) {
      $this->entityManager->persist($record);
    }
    $this->entityManager->flush();

    return $this->view($record);
  }

  public function removeByOrganizationAndEquipment(string $organizationId, string $equipmentId): void
  {
    $record = $this->findRecordByOrganizationAndEquipment($organizationId, $equipmentId);
    if (!$record instanceof MaintenanceScheduleRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method findRecordByOrganizationAndEquipment.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the equipment identifier
   *
   * @return ?MaintenanceScheduleRecord the record, or null when not found
   */
  private function findRecordByOrganizationAndEquipment(string $organizationId, string $equipmentId): ?MaintenanceScheduleRecord
  {
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    /** @var ?MaintenanceScheduleRecord $record */
    $record = $this->entityManager->getRepository(MaintenanceScheduleRecord::class)->findOneBy([
      'organization' => $organization,
      'equipmentId' => $equipmentId,
    ]);

    return $record;
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
  // #endregion
}
