<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Recurrence;

use DateTimeImmutable;

/**
 * Contract InterventionRecurrenceView.
 *
 * Read model for a persisted intervention recurrence.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionRecurrenceView
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $id the recurrence identifier
   * @param string $organizationId the owning organization identifier
   * @param string $templateId the instantiated template identifier
   * @param string $name the intervention name given to each materialized occurrence
   * @param ?string $siteId the site (facility) override, or null to use the template default
   * @param ?string $responsibleId the responsible member override, or null to use the template default
   * @param string $frequency the recurrence frequency value (weekly|monthly|quarterly|semiannual|annual)
   * @param int $interval the interval count multiplying the frequency
   * @param DateTimeImmutable $anchorDate the reference date the recurrence is walked from
   * @param string $timezone the IANA timezone the rule is evaluated in
   * @param int $leadTimeDays the number of days before an occurrence it becomes due for materialization
   * @param DateTimeImmutable $nextOccurrenceAt the next occurrence date
   * @param ?DateTimeImmutable $lastMaterializedAt the last successful materialization date, if any
   * @param bool $isActive whether the recurrence is active
   * @param ?DateTimeImmutable $endAt the optional date after which no further occurrence is materialized
   * @param DateTimeImmutable $createdAt the creation date
   * @param DateTimeImmutable $updatedAt the last update date
   */
  public function __construct(
    public string $id,
    public string $organizationId,
    public string $templateId,
    public string $name,
    public ?string $siteId,
    public ?string $responsibleId,
    public string $frequency,
    public int $interval,
    public DateTimeImmutable $anchorDate,
    public string $timezone,
    public int $leadTimeDays,
    public DateTimeImmutable $nextOccurrenceAt,
    public ?DateTimeImmutable $lastMaterializedAt,
    public bool $isActive,
    public ?DateTimeImmutable $endAt,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
  // #endregion
}
