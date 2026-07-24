<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\RecordInterventionServiceHistory;

use Equipment\Application\Contract\Intervention\ServicedEquipmentEntry;
use Equipment\Application\Port\Outbound\{InterventionServiceReportPort, MaintenanceLogRepositoryPort};
use Equipment\Domain\Model\MaintenanceLog\EquipmentMaintenanceLog;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId};
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Message\{CommandHandler, VoidResult};

use function hash;

/**
 * UseCase RecordInterventionServiceHistoryHandler.
 *
 * Reads back the equipment changes applied by a published intervention
 * ({@see InterventionServiceReportPort}) and appends one completed,
 * point-in-time service entry per serviced equipment to its maintenance log.
 * Each entry is inserted independently and idempotently
 * ({@see MaintenanceLogRepositoryPort::appendInterventionServiceEntry()}), so
 * one duplicate (at-least-once event redelivery, or a later publication
 * re-reading an already-applied change) never blocks the rest.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInterventionServiceHistoryHandler implements CommandHandler
{
  // #region Constants
  /**
   * Prefix mixed into the dedup key so an intervention-service entry can
   * never collide with a dedup key from a different idempotency scheme.
   */
  private const string DEDUP_PREFIX = 'intervention_change:';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionServiceReportPort $serviceReport the intervention service report port
   * @param MaintenanceLogRepositoryPort $maintenanceLogRepository the maintenance log repository port
   * @param UuidFactory $uuidFactory the uuid factory
   */
  public function __construct(
    private InterventionServiceReportPort $serviceReport,
    private MaintenanceLogRepositoryPort $maintenanceLogRepository,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param RecordInterventionServiceHistoryCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(RecordInterventionServiceHistoryCommand $command): VoidResult
  {
    $report = $this->serviceReport->serviceReport($command->interventionId);
    if (null === $report) {
      return new VoidResult();
    }

    $organizationId = EquipmentOrganizationId::fromString($command->organizationId);

    foreach ($report->equipment as $entry) {
      $this->recordOne($entry, $organizationId, $report->number, $report->actorId, $command);
    }

    return new VoidResult();
  }

  /**
   * Method recordOne.
   *
   * @since 1.0.0
   *
   * @param ServicedEquipmentEntry $entry the serviced equipment entry
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param int $interventionNumber the intervention's human-readable number
   * @param ?string $actorId the intervention's responsible member identifier, when set
   * @param RecordInterventionServiceHistoryCommand $command the command value
   */
  private function recordOne(
    ServicedEquipmentEntry $entry,
    EquipmentOrganizationId $organizationId,
    int $interventionNumber,
    ?string $actorId,
    RecordInterventionServiceHistoryCommand $command,
  ): void {
    /** @var MaintenanceLogId $logId */
    $logId = $this->uuidFactory->create(MaintenanceLogId::class);

    $log = EquipmentMaintenanceLog::recordInterventionService(
      id: $logId,
      equipmentId: EquipmentId::fromString($entry->equipmentId),
      organizationId: $organizationId,
      occurredAt: $command->occurredAt,
      interventionId: $command->interventionId,
      interventionNumber: $interventionNumber,
      workItemAction: $entry->action,
      actorId: $actorId,
    );

    $this->maintenanceLogRepository->appendInterventionServiceEntry($log, $this->dedupKey($entry->changeToken));
  }

  /**
   * Method dedupKey.
   *
   * @since 1.0.0
   *
   * @param string $changeToken the applied change's identifier
   *
   * @return string the dedup key guarding the idempotent insert
   */
  private function dedupKey(string $changeToken): string
  {
    return hash('sha1', self::DEDUP_PREFIX . $changeToken);
  }
  // #endregion
}
