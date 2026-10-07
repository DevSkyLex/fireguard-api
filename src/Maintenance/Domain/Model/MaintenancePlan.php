<?php

declare(strict_types=1);

namespace Maintenance\Domain\Model;

use DateTimeImmutable;
use Maintenance\Domain\ValueObject\{MaintenanceOperationKind, PlanCadence};
use Shared\Domain\Exception\InvalidValueException;
use Shared\Domain\ValueObject\Uuid;

use function max;
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
  // #region Constructor
  /**
   * Method __construct
   *
   * Restores a plan without deriving its identity or calendar from equipment type.
   *
   * @access private
   *
   * @param string $id the plan UUID
   * @param string $organizationId the owning organization UUID
   * @param string $equipmentId the equipment UUID
   * @param string $name the operation's display name
   * @param MaintenanceOperationKind $kind the independently scheduled operation
   * @param PlanCadence $cadence the explicit interval and calculation mode
   * @param ?DateTimeImmutable $anchorAt the original fixed due date, absent for unscheduled historical rows
   * @param ?DateTimeImmutable $nextDueAt the next due date
   * @param DateTimeImmutable $createdAt the creation instant
   * @param bool $legacy whether the plan preserves historical sliding arithmetic
   * @param bool $archived whether generation has been stopped
   *
   * @return void
   */
  private function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    public readonly string $equipmentId,
    public readonly string $name,
    public readonly MaintenanceOperationKind $kind,
    public readonly PlanCadence $cadence,
    public readonly ?DateTimeImmutable $anchorAt,
    private ?DateTimeImmutable $nextDueAt,
    public readonly DateTimeImmutable $createdAt,
    public readonly bool $legacy,
    private bool $archived,
  ) {
    new Uuid($id);
    new Uuid($organizationId);
    new Uuid($equipmentId);

    if ('' === trim($name) || mb_strlen($name) > 200) {
      throw InvalidValueException::because('A maintenance plan needs a name of at most 200 characters.');
    }

    if ($legacy !== $cadence->legacy || (!$legacy && (null === $anchorAt || null === $nextDueAt))) {
      throw InvalidValueException::because('A fixed plan needs an anchor and next due date; its calculation mode must match its cadence.');
    }

    if (null !== $anchorAt && null !== $nextDueAt && !$legacy && $nextDueAt < $anchorAt) {
      throw InvalidValueException::because('A fixed plan cannot be due before its original anchor.');
    }

    if (null !== $anchorAt && null !== $nextDueAt && !$legacy && $cadence->preview($anchorAt, $nextDueAt, 1)[0]->format('U.u') !== $nextDueAt->format('U.u')) {
      throw InvalidValueException::because('A fixed plan must be due on a calendar slot derived from its original anchor.');
    }
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
   * @param string $id the plan UUID
   * @param string $organizationId the owning organization UUID
   * @param string $equipmentId the equipment UUID
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
    string $id,
    string $organizationId,
    string $equipmentId,
    string $name,
    MaintenanceOperationKind $kind,
    PlanCadence $cadence,
    ?DateTimeImmutable $firstDueAt,
    DateTimeImmutable $now,
    bool $legacy = false,
  ): self {
    return new self($id, $organizationId, $equipmentId, trim($name), $kind, $cadence, $firstDueAt, $firstDueAt, $now, $legacy, false);
  }

  /**
   * Method reconstitute
   *
   * Restores persisted state only when its next date belongs to the anchored
   * calendar, preserving the original anchor after a month-end clamp.
   *
   * @access public
   *
   * @param string $id the plan UUID
   * @param string $organizationId the owning organization UUID
   * @param string $equipmentId the equipment UUID
   * @param string $name the operation's display name
   * @param MaintenanceOperationKind $kind the independently scheduled operation
   * @param PlanCadence $cadence the explicit interval
   * @param ?DateTimeImmutable $firstDueAt the original calendar anchor
   * @param ?DateTimeImmutable $nextDueAt the stored due date
   * @param DateTimeImmutable $createdAt the original creation instant
   * @param bool $legacy whether the plan preserves historical sliding arithmetic
   * @param bool $archived whether generation is stopped
   *
   * @return self the restored plan
   */
  public static function reconstitute(
    string $id,
    string $organizationId,
    string $equipmentId,
    string $name,
    MaintenanceOperationKind $kind,
    PlanCadence $cadence,
    ?DateTimeImmutable $firstDueAt,
    ?DateTimeImmutable $nextDueAt,
    DateTimeImmutable $createdAt,
    bool $legacy,
    bool $archived,
  ): self {
    return new self($id, $organizationId, $equipmentId, $name, $kind, $cadence, $firstDueAt, $nextDueAt, $createdAt, $legacy, $archived);
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
    return $this->nextDueAt;
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
    if (null === $this->nextDueAt) {
      return [];
    }

    return $this->cadence->preview($this->anchorAt ?? $this->nextDueAt, $this->nextDueAt, $count);
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
    if ($this->legacy) {
      $this->nextDueAt = $this->cadence->addTo($validatedAt);

      return;
    }

    if (null === $this->anchorAt || null === $this->nextDueAt) {
      throw InvalidValueException::because('A fixed maintenance plan needs an explicit calendar.');
    }

    $this->nextDueAt = $this->cadence->nextAfter($this->anchorAt, max($this->nextDueAt, $validatedAt));
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
    return !$this->archived && !$siteArchived && !$equipmentRetired && null !== $this->nextDueAt;
  }
  // #endregion
}
