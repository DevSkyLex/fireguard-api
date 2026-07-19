<?php

declare(strict_types=1);

namespace Intervention\Application\Port\Outbound;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\{InterventionRecurrencePage, InterventionRecurrenceView};

/**
 * Interface InterventionRecurrencePort.
 *
 * Persists and reads intervention recurrences, and backs the recurring
 * materializer's idempotence guard: one run row per
 * `(recurrence, occurrence date)` pair, enforced by a unique constraint on
 * `intervention_recurrence_runs` so a Messenger retry or an overlapping sweep
 * tick can never materialize the same occurrence twice.
 *
 * @category Interface
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface InterventionRecurrencePort
{
  // #region CRUD
  /**
   * Method create.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param string $templateId the template id value
   * @param string $name the name value
   * @param ?string $siteId the site override value
   * @param ?string $responsibleId the responsible member override value
   * @param string $frequency the recurrence frequency value
   * @param int $interval the interval count value
   * @param DateTimeImmutable $anchorDate the anchor date value
   * @param string $timezone the IANA timezone value
   * @param int $leadTimeDays the lead time in days value
   * @param DateTimeImmutable $nextOccurrenceAt the initial next occurrence value
   * @param ?DateTimeImmutable $endAt the optional end date value
   *
   * @return InterventionRecurrenceView the created recurrence view
   */
  public function create(
    string $organizationId,
    string $templateId,
    string $name,
    ?string $siteId,
    ?string $responsibleId,
    string $frequency,
    int $interval,
    DateTimeImmutable $anchorDate,
    string $timezone,
    int $leadTimeDays,
    DateTimeImmutable $nextOccurrenceAt,
    ?DateTimeImmutable $endAt,
  ): InterventionRecurrenceView;

  /**
   * Method update.
   *
   * Applies a merge-patch: a field is only changed when its `$has*` flag is
   * true.
   *
   * @since 1.0.0
   *
   * @param string $id the recurrence id value
   * @param ?string $name the name value, only applied when `$hasName` is true
   * @param ?string $siteId the site override value, only applied when `$hasSiteId` is true
   * @param ?string $responsibleId the responsible member override value, only applied when `$hasResponsibleId` is true
   * @param ?string $frequency the recurrence frequency value, only applied when `$hasFrequency` is true
   * @param ?int $interval the interval count value, only applied when `$hasInterval` is true
   * @param ?DateTimeImmutable $anchorDate the anchor date value, only applied when `$hasAnchorDate` is true
   * @param ?string $timezone the IANA timezone value, only applied when `$hasTimezone` is true
   * @param ?int $leadTimeDays the lead time in days value, only applied when `$hasLeadTimeDays` is true
   * @param ?DateTimeImmutable $nextOccurrenceAt the recomputed next occurrence value, only applied when `$hasNextOccurrenceAt` is true
   * @param ?DateTimeImmutable $endAt the end date value, only applied when `$hasEndAt` is true
   * @param ?bool $isActive the active flag value, only applied when `$hasIsActive` is true
   * @param bool $hasName whether the name field was present in the merge-patch request
   * @param bool $hasSiteId whether the site field was present in the merge-patch request
   * @param bool $hasResponsibleId whether the responsible field was present in the merge-patch request
   * @param bool $hasFrequency whether the frequency field was present in the merge-patch request
   * @param bool $hasInterval whether the interval field was present in the merge-patch request
   * @param bool $hasAnchorDate whether the anchor date field was present in the merge-patch request
   * @param bool $hasTimezone whether the timezone field was present in the merge-patch request
   * @param bool $hasLeadTimeDays whether the lead time field was present in the merge-patch request
   * @param bool $hasNextOccurrenceAt whether the next occurrence was recomputed by the handler
   * @param bool $hasEndAt whether the end date field was present in the merge-patch request
   * @param bool $hasIsActive whether the isActive field was present in the merge-patch request
   *
   * @return InterventionRecurrenceView the updated recurrence view
   */
  public function update(
    string $id,
    ?string $name,
    ?string $siteId,
    ?string $responsibleId,
    ?string $frequency,
    ?int $interval,
    ?DateTimeImmutable $anchorDate,
    ?string $timezone,
    ?int $leadTimeDays,
    ?DateTimeImmutable $nextOccurrenceAt,
    ?DateTimeImmutable $endAt,
    ?bool $isActive,
    bool $hasName,
    bool $hasSiteId,
    bool $hasResponsibleId,
    bool $hasFrequency,
    bool $hasInterval,
    bool $hasAnchorDate,
    bool $hasTimezone,
    bool $hasLeadTimeDays,
    bool $hasNextOccurrenceAt,
    bool $hasEndAt,
    bool $hasIsActive,
  ): InterventionRecurrenceView;

  /**
   * Method delete.
   *
   * @since 1.0.0
   *
   * @param string $id the recurrence id value
   */
  public function delete(string $id): void;

  /**
   * Method find.
   *
   * @since 1.0.0
   *
   * @param string $id the recurrence id value
   *
   * @return ?InterventionRecurrenceView the recurrence view, or null when not found
   */
  public function find(string $id): ?InterventionRecurrenceView;

  /**
   * Method list.
   *
   * Lists an organization's recurrences, ordered by name.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   * @param ?bool $isActive an optional active-state filter
   *
   * @return InterventionRecurrencePage the recurrence page result
   */
  public function list(string $organizationId, int $page, int $itemsPerPage, ?bool $isActive = null): InterventionRecurrencePage;
  // #endregion

  // #region Materialization
  /**
   * Method pageDueForMaterialization.
   *
   * Pages through active recurrences whose lead-time window has opened
   * (`next_occurrence_at - lead_time_days <= $now`) and whose end date, if
   * any, has not yet passed the next occurrence — ordered by id, for the
   * sweep to process in bounded batches.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $now the current instant
   * @param int $limit the maximum number of results
   * @param int $offset the result offset
   *
   * @return InterventionRecurrencePage the due recurrence page result
   */
  public function pageDueForMaterialization(DateTimeImmutable $now, int $limit, int $offset): InterventionRecurrencePage;

  /**
   * Method reserveRun.
   *
   * Idempotently claims a `(recurrence, occurrence date)` pair by inserting
   * a placeholder run row, immediately updated to its final outcome by
   * {@see self::markRunSucceeded()} or {@see self::markRunFailed()}. Returns
   * `null` when the pair is already claimed (a previous tick already
   * materialized or is materializing this occurrence), so the caller must
   * skip the occurrence entirely rather than reprocess it.
   *
   * @since 1.0.0
   *
   * @param string $recurrenceId the recurrence id value
   * @param DateTimeImmutable $occurrenceDate the occurrence date value
   *
   * @return ?string the reserved run id, or null when already claimed
   */
  public function reserveRun(string $recurrenceId, DateTimeImmutable $occurrenceDate): ?string;

  /**
   * Method markRunSucceeded.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   * @param string $interventionId the materialized intervention id value
   */
  public function markRunSucceeded(string $runId, string $interventionId): void;

  /**
   * Method markRunFailed.
   *
   * @since 1.0.0
   *
   * @param string $runId the run id value
   * @param string $error the failure reason value
   */
  public function markRunFailed(string $runId, string $error): void;

  /**
   * Method advanceNextOccurrence.
   *
   * Advances a recurrence's `next_occurrence_at`, both on materialization
   * success and failure (no infinite retry on a permanently broken
   * occurrence). `lastMaterializedAt` is only set on success.
   *
   * @since 1.0.0
   *
   * @param string $recurrenceId the recurrence id value
   * @param DateTimeImmutable $nextOccurrenceAt the recomputed next occurrence value
   * @param ?DateTimeImmutable $lastMaterializedAt the materialization instant, only set on success
   */
  public function advanceNextOccurrence(string $recurrenceId, DateTimeImmutable $nextOccurrenceAt, ?DateTimeImmutable $lastMaterializedAt): void;
  // #endregion
}
