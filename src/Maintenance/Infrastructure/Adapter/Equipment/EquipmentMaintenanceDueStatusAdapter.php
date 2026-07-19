<?php

declare(strict_types=1);

namespace Maintenance\Infrastructure\Adapter\Equipment;

use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Port\Outbound\MaintenanceDueStatusPort;
use Maintenance\Domain\ValueObject\MaintenanceDueStatus;
use Maintenance\Infrastructure\Persistence\Doctrine\Record\MaintenanceScheduleRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Adapter EquipmentMaintenanceDueStatusAdapter.
 *
 * Implements the Equipment module's cross-module due-status port. A single
 * DQL query batch-resolves every requested equipment id in one round trip
 * (never per row); ids with no matching `MaintenanceScheduleRecord` (never
 * reconciled by the recurring sweep yet, or genuinely untracked) default to
 * `unscheduled` so the caller never sees an absent entry or a null.
 *
 * @category Adapter
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMaintenanceDueStatusAdapter implements MaintenanceDueStatusPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
  }
  // #endregion

  // #region Methods
  public function dueStatusesForEquipment(string $organizationId, array $equipmentIds): array
  {
    if ([] === $equipmentIds) {
      return [];
    }

    $dueStatuses = [];
    foreach ($equipmentIds as $equipmentId) {
      $dueStatuses[$equipmentId] = MaintenanceDueStatus::UNSCHEDULED->value;
    }

    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, $organizationId);

    $qb = $this->entityManager->createQueryBuilder()
      ->select('s.equipmentId AS equipmentId, s.dueStatus AS dueStatus')
      ->from(MaintenanceScheduleRecord::class, 's')
      ->where('s.organization = :organization')
      ->andWhere('s.equipmentId IN (:equipmentIds)')
      ->setParameter('organization', $organization)
      ->setParameter('equipmentIds', $equipmentIds);

    /** @var list<array{equipmentId: string, dueStatus: string}> $rows */
    $rows = $qb->getQuery()->getArrayResult();

    foreach ($rows as $row) {
      $dueStatuses[$row['equipmentId']] = $row['dueStatus'];
    }

    return $dueStatuses;
  }
  // #endregion
}
