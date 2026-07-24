<?php

declare(strict_types=1);

namespace Equipment\Application\Service;

use Equipment\Application\Port\Inbound\EquipmentMaintenanceLogSynchronizerPort;
use Equipment\Application\Port\Outbound\MaintenanceLogRepositoryPort;
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId};
use Shared\Application\Factory\UuidFactory;

/**
 * Service EquipmentMaintenanceLogSynchronizer.
 *
 * Keeps the maintenance-log history in step with an equipment status transition,
 * mirroring the Commission / PutUnderMaintenance / Decommission handlers so the
 * flat write surfaces (canonical mutation processor, intervention publication
 * adapter) no longer corrupt the maintenance history: entering `under_maintenance`
 * opens a log, leaving it (to operational, in_stock, or decommissioned) closes the
 * open one. `commissionedAt` stamping stays with each caller as it is a field on
 * the persistence record rather than a log concern.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentMaintenanceLogSynchronizer implements EquipmentMaintenanceLogSynchronizerPort
{
  private const string UNDER_MAINTENANCE = 'under_maintenance';

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the EquipmentMaintenanceLogSynchronizer class.
   *
   * @since 1.0.0
   *
   * @param MaintenanceLogRepositoryPort $maintenanceLogRepository the maintenance log repository port
   * @param UuidFactory $uuidFactory the uuid factory
   */
  public function __construct(
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method syncForStatusTransition.
   *
   * Opens or closes the maintenance log for a status change. A no-op when the
   * status is unchanged or the transition does not enter or leave maintenance.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the equipment identifier
   * @param string $organizationId the organization identifier
   * @param string $previousStatus the status before the change
   * @param string $newStatus the status after the change
   */
  public function syncForStatusTransition(
    string $equipmentId,
    string $organizationId,
    string $previousStatus,
    string $newStatus,
  ): void {
    if ($previousStatus === $newStatus) {
      return;
    }

    if (self::UNDER_MAINTENANCE === $newStatus) {
      /** @var MaintenanceLogId $logId */
      $logId = $this->uuidFactory->create(MaintenanceLogId::class);
      $this->maintenanceLogRepository->save(EquipmentMaintenanceLog::open(
        $logId,
        EquipmentId::fromString($equipmentId),
        EquipmentOrganizationId::fromString($organizationId),
      ));

      return;
    }

    if (self::UNDER_MAINTENANCE === $previousStatus) {
      $openLog = $this->maintenanceLogRepository->findOpenByEquipmentId(EquipmentId::fromString($equipmentId));
      if (null !== $openLog) {
        $openLog->close();
        $this->maintenanceLogRepository->save($openLog);
      }
    }
  }
  // #endregion
}
