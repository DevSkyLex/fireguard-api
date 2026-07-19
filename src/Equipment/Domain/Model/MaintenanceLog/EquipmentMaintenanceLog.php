<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId, MaintenanceLogSource};

/**
 * Model EquipmentMaintenanceLog.
 *
 * Records either a maintenance window for an equipment item (created when
 * equipment transitions to UNDER_MAINTENANCE, closed on commissioning back —
 * `source = status_transition`) or a point-in-time regulatory service record
 * synthesized from a published intervention (`source = intervention`, always
 * completed: `startedAt === completedAt`).
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentMaintenanceLog
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceLogId $id the log entry identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $startedAt when maintenance began
   * @param ?DateTimeImmutable $completedAt when maintenance ended (null while ongoing)
   * @param MaintenanceLogSource $source what produced this entry
   * @param ?string $interventionId the source intervention identifier (intervention entries only)
   * @param ?int $interventionNumber the source intervention's human-readable number (intervention entries only)
   * @param ?string $workItemAction the linked work item's action, when known (intervention entries only)
   * @param ?string $actorId the acting user identifier, when known (intervention entries only)
   * @param ?string $summary a free-form summary of the service performed
   */
  private function __construct(
    private MaintenanceLogId $id,
    private EquipmentId $equipmentId,
    private EquipmentOrganizationId $organizationId,
    private DateTimeImmutable $startedAt,
    private ?DateTimeImmutable $completedAt = null,
    private MaintenanceLogSource $source = MaintenanceLogSource::STATUS_TRANSITION,
    private ?string $interventionId = null,
    private ?int $interventionNumber = null,
    private ?string $workItemAction = null,
    private ?string $actorId = null,
    private ?string $summary = null,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method open.
   *
   * Opens a new maintenance log entry.
   *
   * @since 1.0.0
   *
   * @param MaintenanceLogId $id the log entry identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   *
   * @return self the opened log entry
   */
  public static function open(
    MaintenanceLogId $id,
    EquipmentId $equipmentId,
    EquipmentOrganizationId $organizationId,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
      organizationId: $organizationId,
      startedAt: new DateTimeImmutable(),
      completedAt: null,
      source: MaintenanceLogSource::STATUS_TRANSITION,
    );
  }

  /**
   * Method recordInterventionService.
   *
   * Builds a completed, point-in-time service entry synthesized from a
   * published intervention's applied equipment change. Unlike open()/close(),
   * this entry is born already completed: `startedAt === completedAt ===
   * $occurredAt`, since the intervention publication is the recorded event
   * itself, not a window with a later closing action.
   *
   * @since 1.0.0
   *
   * @param MaintenanceLogId $id the log entry identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $occurredAt when the intervention published (the service instant)
   * @param string $interventionId the source intervention identifier
   * @param int $interventionNumber the source intervention's human-readable number
   * @param string $workItemAction the derived or linked work item action label
   * @param ?string $actorId the acting user identifier, when known
   * @param ?string $summary a free-form summary of the service performed
   *
   * @return self the completed intervention service entry
   */
  public static function recordInterventionService(
    MaintenanceLogId $id,
    EquipmentId $equipmentId,
    EquipmentOrganizationId $organizationId,
    DateTimeImmutable $occurredAt,
    string $interventionId,
    int $interventionNumber,
    string $workItemAction,
    ?string $actorId,
    ?string $summary = null,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
      organizationId: $organizationId,
      startedAt: $occurredAt,
      completedAt: $occurredAt,
      source: MaintenanceLogSource::INTERVENTION,
      interventionId: $interventionId,
      interventionNumber: $interventionNumber,
      workItemAction: $workItemAction,
      actorId: $actorId,
      summary: $summary,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes a log entry from persisted state.
   *
   * @since 1.0.0
   *
   * @param MaintenanceLogId $id the log entry identifier
   * @param EquipmentId $equipmentId the equipment identifier
   * @param EquipmentOrganizationId $organizationId the organization identifier
   * @param DateTimeImmutable $startedAt when maintenance began
   * @param ?DateTimeImmutable $completedAt when maintenance ended
   * @param MaintenanceLogSource $source what produced this entry
   * @param ?string $interventionId the source intervention identifier, when set
   * @param ?int $interventionNumber the source intervention's human-readable number, when set
   * @param ?string $workItemAction the linked work item action, when set
   * @param ?string $actorId the acting user identifier, when set
   * @param ?string $summary a free-form summary of the service performed, when set
   *
   * @return self the reconstituted log entry
   */
  public static function reconstitute(
    MaintenanceLogId $id,
    EquipmentId $equipmentId,
    EquipmentOrganizationId $organizationId,
    DateTimeImmutable $startedAt,
    ?DateTimeImmutable $completedAt,
    MaintenanceLogSource $source = MaintenanceLogSource::STATUS_TRANSITION,
    ?string $interventionId = null,
    ?int $interventionNumber = null,
    ?string $workItemAction = null,
    ?string $actorId = null,
    ?string $summary = null,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
      organizationId: $organizationId,
      startedAt: $startedAt,
      completedAt: $completedAt,
      source: $source,
      interventionId: $interventionId,
      interventionNumber: $interventionNumber,
      workItemAction: $workItemAction,
      actorId: $actorId,
      summary: $summary,
    );
  }

  /**
   * Method close.
   *
   * Closes the maintenance log entry by recording the completion time.
   *
   * @since 1.0.0
   */
  public function close(): void
  {
    $this->completedAt = new DateTimeImmutable();
  }

  /**
   * Method id.
   *
   * @since 1.0.0
   */
  public function id(): MaintenanceLogId
  {
    return $this->id;
  }

  /**
   * Method equipmentId.
   *
   * @since 1.0.0
   */
  public function equipmentId(): EquipmentId
  {
    return $this->equipmentId;
  }

  /**
   * Method organizationId.
   *
   * @since 1.0.0
   */
  public function organizationId(): EquipmentOrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method startedAt.
   *
   * @since 1.0.0
   */
  public function startedAt(): DateTimeImmutable
  {
    return $this->startedAt;
  }

  /**
   * Method completedAt.
   *
   * @since 1.0.0
   */
  public function completedAt(): ?DateTimeImmutable
  {
    return $this->completedAt;
  }

  /**
   * Method isOngoing.
   *
   * @since 1.0.0
   */
  public function isOngoing(): bool
  {
    return null === $this->completedAt;
  }

  /**
   * Method source.
   *
   * @since 1.0.0
   */
  public function source(): MaintenanceLogSource
  {
    return $this->source;
  }

  /**
   * Method interventionId.
   *
   * @since 1.0.0
   */
  public function interventionId(): ?string
  {
    return $this->interventionId;
  }

  /**
   * Method interventionNumber.
   *
   * @since 1.0.0
   */
  public function interventionNumber(): ?int
  {
    return $this->interventionNumber;
  }

  /**
   * Method workItemAction.
   *
   * @since 1.0.0
   */
  public function workItemAction(): ?string
  {
    return $this->workItemAction;
  }

  /**
   * Method actorId.
   *
   * @since 1.0.0
   */
  public function actorId(): ?string
  {
    return $this->actorId;
  }

  /**
   * Method summary.
   *
   * @since 1.0.0
   */
  public function summary(): ?string
  {
    return $this->summary;
  }
  // #endregion
}
