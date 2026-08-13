<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Reminder;

use DateTimeImmutable;

/**
 * Contract InterventionReminderCandidate.
 *
 * Read model for an intervention approaching or past its `dueAt`, as selected
 * by {@see \Intervention\Application\Port\Outbound\InterventionReminderPort}.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionReminderCandidate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the intervention identifier
   * @param string $organizationId the owning organization identifier
   * @param int $number the intervention's human-readable number
   * @param string $name the intervention name
   * @param DateTimeImmutable $dueAt the due date the reminder was selected for
   * @param ?string $responsibleId the responsible member identifier, if any
   * @param list<string> $participants the intervention's participant member identifiers
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public int $number,
    public string $name,
    public DateTimeImmutable $dueAt,
    public ?string $responsibleId,
    public array $participants,
  ) {
  }
  // #endregion
}
