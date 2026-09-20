<?php

declare(strict_types=1);

namespace Intervention\Domain\Model\TimeEntry;

use DateTimeImmutable;
use Intervention\Domain\Exception\{InterventionPreconditionFailedException, InterventionValidationException};

use function mb_strlen;

/**
 * Model TimeEntry: an independently versioned record of actual work.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeEntry
{
  // #region Methods
  /**
   * @since 1.0.0
   *
   * @param string $id stable resource identifier, retained across idempotent retries
   * @param string $workItemId intervention task associated with this contribution
   * @param string $memberId organization member whose work or capacity is represented
   * @param string $workedOn organization-local calendar date on which the work was performed
   * @param int $minutes duration in whole minutes
   * @param ?string $note optional context supplied with the time entry
   * @param int $revision revision of this resource, used independently of other aggregates
   * @param bool $cancelled whether the entry is cancelled while its history remains retained
   */
  public function __construct(
    public string $id,
    public string $workItemId,
    public string $memberId,
    public string $workedOn,
    public int $minutes,
    public ?string $note,
    public int $revision = 1,
    public bool $cancelled = false,
  ) {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $workedOn);
    if (false === $date || $date->format('Y-m-d') !== $workedOn || $minutes <= 0 || $minutes > 1440 || $revision < 1 || (null !== $note && mb_strlen($note) > 2000)) {
      throw new InterventionValidationException('A time entry needs a valid work date, 1–1440 minutes and a note of at most 2000 characters.');
    }
  }

  /**
   * Creates a corrected time-entry revision without changing the operational task.
   *
   * @since 1.0.0
   *
   * @param int $expectedRevision last explicitly reviewed time-entry revision
   * @param string $workedOn organization-local calendar date on which the work was performed
   * @param int $minutes duration in whole minutes
   * @param ?string $note optional context supplied with the time entry
   *
   * @return self corrected entry with its independent revision advanced
   */
  public function correct(int $expectedRevision, string $workedOn, int $minutes, ?string $note): self
  {
    $this->assertRevision($expectedRevision);
    if ($this->cancelled) {
      throw new InterventionValidationException('A cancelled time entry cannot be corrected.');
    }

    return new self($this->id, $this->workItemId, $this->memberId, $workedOn, $minutes, $note, $this->revision + 1);
  }

  /**
   * Cancels a time entry without removing its retained history.
   *
   * @since 1.0.0
   *
   * @param int $expectedRevision last explicitly reviewed time-entry revision
   *
   * @return self cancelled entry retaining its identity and historical contribution
   */
  public function cancel(int $expectedRevision): self
  {
    $this->assertRevision($expectedRevision);

    return $this->cancelled ? $this : new self($this->id, $this->workItemId, $this->memberId, $this->workedOn, $this->minutes, $this->note, $this->revision + 1, true);
  }

  /**
   * Rejects a stale time-entry revision instead of silently rebasing it.
   *
   * @since 1.0.0
   *
   * @param int $expectedRevision last explicitly reviewed time-entry revision
   *
   * @return void completes without returning a value
   */
  private function assertRevision(int $expectedRevision): void
  {
    if ($expectedRevision !== $this->revision) {
      throw new InterventionPreconditionFailedException('The time entry changed. Review the current version before correcting it.');
    }
  }
  // #endregion
}
