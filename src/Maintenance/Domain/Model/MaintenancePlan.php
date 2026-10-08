<?php

declare(strict_types=1);

namespace Maintenance\Domain\Model;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\{MaintenanceOperationKind, MaintenancePlanCalendar, MaintenancePlanIdentity, PlanCadence};
use Shared\Domain\Exception\InvalidValueException;

use function mb_strlen;
use function trim;

/**
 * Class MaintenancePlan
 *
 * Owns one equipment operation and preserves its independent calendar anchor.
 *
 * @category Model
 */
final class MaintenancePlan
{
  // #region Properties
  /**
   * Property id
   *
   * Identifies the operation within its validated ownership scope.
   */
  public readonly string $id;

  /**
   * Property organizationId
   *
   * Identifies the organization owning the operation.
   */
  public readonly string $organizationId;

  /**
   * Property equipmentId
   *
   * Identifies the equipment whose operation is independently scheduled.
   */
  public readonly string $equipmentId;

  /**
   * Property cadence
   *
   * Exposes the immutable interval and calculation mode.
   */
  public readonly PlanCadence $cadence;

  /**
   * Property anchorAt
   *
   * Retains the original calendar anchor through month-end clamping.
   */
  public readonly ?DateTimeImmutable $anchorAt;

  /**
   * Property legacy
   *
   * Exposes the explicit historical arithmetic marker.
   */
  public readonly bool $legacy;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * Restores a plan without deriving its identity or calendar from equipment type.
   *
   * @access private
   *
   * @param MaintenancePlanIdentity $identity the validated plan ownership scope
   * @param string $name the operation's display name
   * @param MaintenanceOperationKind $kind the independently scheduled operation
   * @param MaintenancePlanCalendar $calendar the validated anchored or historical calendar
   * @param DateTimeImmutable $createdAt the creation instant
   * @param bool $archived whether generation has been stopped
   *
   * @return void
   */
  private function __construct(
    MaintenancePlanIdentity $identity,
    public readonly string $name,
    public readonly MaintenanceOperationKind $kind,
    private MaintenancePlanCalendar $calendar,
    public readonly DateTimeImmutable $createdAt,
    private bool $archived,
  ) {
    if ('' === trim($name) || mb_strlen($name) > 200) {
      throw InvalidValueException::because('A maintenance plan needs a name of at most 200 characters.');
    }

    $this->id = $identity->id;
    $this->organizationId = $identity->organizationId;
    $this->equipmentId = $identity->equipmentId;
    $this->cadence = $calendar->cadence;
    $this->anchorAt = $calendar->anchorAt;
    $this->legacy = $calendar->legacy;
  }
  // #endregion

  // #region Methods
  /**
   * Method create
   *
   * Starts a new independent operation with an explicit first due date.
   *
   * @access public
   *
   * @param MaintenancePlanIdentity $identity the validated plan ownership scope
   * @param string $name the operation's display name
   * @param MaintenanceOperationKind $kind the independently scheduled operation
   * @param PlanCadence $cadence the explicit interval
   * @param ?DateTimeImmutable $firstDueAt the first due date; null is reserved for legacy plans
   * @param DateTimeImmutable $now the creation instant
   * @param bool $legacy whether the cadence preserves historical sliding arithmetic
   *
   * @return self the new plan
   */
  public static function create(
    MaintenancePlanIdentity $identity,
    string $name,
    MaintenanceOperationKind $kind,
    PlanCadence $cadence,
    ?DateTimeImmutable $firstDueAt,
    DateTimeImmutable $now,
    bool $legacy = false,
  ): self {
    return new self($identity, trim($name), $kind, MaintenancePlanCalendar::start($cadence, $firstDueAt, $legacy), $now, false);
  }

  /**
   * Method reconstitute
   *
   * Restores persisted state only when its next date belongs to the anchored
   * calendar, preserving the original anchor after a month-end clamp.
   *
   * @access public
   *
   * @param MaintenancePlanIdentity $identity the validated plan ownership scope
   * @param string $name the operation's display name
   * @param MaintenanceOperationKind $kind the independently scheduled operation
   * @param MaintenancePlanCalendar $calendar the restored calendar with its original anchor and due slot
   * @param DateTimeImmutable $createdAt the original creation instant
   * @param bool $archived whether generation is stopped
   *
   * @return self the restored plan
   */
  public static function reconstitute(
    MaintenancePlanIdentity $identity,
    string $name,
    MaintenanceOperationKind $kind,
    MaintenancePlanCalendar $calendar,
    DateTimeImmutable $createdAt,
    bool $archived,
  ): self {
    return new self($identity, $name, $kind, $calendar, $createdAt, $archived);
  }

  /**
   * Method nextDueAt
   *
   * Returns the stored due date without recalculating from unrelated inspections.
   *
   * @access public
   *
   * @return ?DateTimeImmutable the next due date
   */
  public function nextDueAt(): ?DateTimeImmutable
  {
    return $this->calendar->nextDueAt;
  }

  /**
   * Method isArchived
   *
   * Exposes whether the plan has been stopped while retaining its history.
   *
   * @access public
   *
   * @return bool whether the plan is archived
   */
  public function isArchived(): bool
  {
    return $this->archived;
  }

  /**
   * Method preview
   *
   * Previews dates from the stored due date; unscheduled legacy rows stay empty.
   *
   * @access public
   *
   * @param int $count the requested number of dates
   *
   * @return list<DateTimeImmutable> the upcoming calendar slots
   */
  public function preview(int $count = 3): array
  {
    return $this->calendar->preview($count);
  }

  /**
   * Method complete
   *
   * Advances a validated occurrence exactly once; callers retain occurrence
   * completion receipts for idempotency. Early completion also advances its slot.
   *
   * @access public
   *
   * @param DateTimeImmutable $validatedAt the validated result instant
   *
   * @return void
   */
  public function complete(DateTimeImmutable $validatedAt): void
  {
    $this->calendar = $this->calendar->advance($validatedAt);
  }

  /**
   * Method archive
   *
   * Stops future generation without deleting historical occurrences.
   *
   * @access public
   *
   * @return void
   */
  public function archive(): void
  {
    $this->archived = true;
  }

  /**
   * Method canGenerate
   *
   * Applies archive and lifecycle suspension before any occurrence reservation.
   *
   * @access public
   *
   * @param bool $siteArchived whether the site's generation is suspended
   * @param bool $equipmentRetired whether the equipment has left service
   *
   * @return bool whether generation is allowed
   */
  public function canGenerate(bool $siteArchived, bool $equipmentRetired): bool
  {
    return !$this->archived && !$siteArchived && !$equipmentRetired && null !== $this->calendar->nextDueAt;
  }
  // #endregion
}
