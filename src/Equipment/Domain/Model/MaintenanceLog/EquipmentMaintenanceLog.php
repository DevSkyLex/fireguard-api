<?php

declare(strict_types=1);

namespace Equipment\Domain\Model\MaintenanceLog;

use DateTimeImmutable;
use Equipment\Domain\ValueObject\{EquipmentId, EquipmentOrganizationId, MaintenanceLogId};

/**
 * Model EquipmentMaintenanceLog.
 *
 * Records a single maintenance window for an equipment item.
 * A log entry is created when equipment transitions to UNDER_MAINTENANCE
 * and is closed (completedAt set) when equipment is commissioned back.
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
   */
  private function __construct(
    private MaintenanceLogId $id,
    private EquipmentId $equipmentId,
    private EquipmentOrganizationId $organizationId,
    private DateTimeImmutable $startedAt,
    private ?DateTimeImmutable $completedAt = null,
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
   *
   * @return self the reconstituted log entry
   */
  public static function reconstitute(
    MaintenanceLogId $id,
    EquipmentId $equipmentId,
    EquipmentOrganizationId $organizationId,
    DateTimeImmutable $startedAt,
    ?DateTimeImmutable $completedAt,
  ): self {
    return new self(
      id: $id,
      equipmentId: $equipmentId,
      organizationId: $organizationId,
      startedAt: $startedAt,
      completedAt: $completedAt,
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
  // #endregion
}
