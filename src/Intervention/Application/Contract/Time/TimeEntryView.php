<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Time;

/**
 * TimeEntryView.
 *
 * @category Intervention
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TimeEntryView
{
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
   * @param string $createdBy member who authored the initial entry
   * @param string $updatedBy member who authored the latest version
   * @param string $createdAt timestamp when the resource was created
   * @param string $updatedAt timestamp of the latest persisted version
   * @param list<TimeEntryVersionView> $versions
   */
  public function __construct(
    public string $id,
    public string $workItemId,
    public string $memberId,
    public string $workedOn,
    public int $minutes,
    public ?string $note,
    public int $revision,
    public bool $cancelled,
    public string $createdBy,
    public string $updatedBy,
    public string $createdAt,
    public string $updatedAt,
    public array $versions,
  ) {
  }
}
