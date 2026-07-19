<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\Service\{InterventionRecurrenceNotifier, InterventionTemplateInstantiator};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use Intervention\Domain\ValueObject\RecurrenceRule;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Throwable;

use function count;

/**
 * UseCase MaterializeDueRecurrencesHandler.
 *
 * Idempotent, safe-to-re-run recurring sweep: pages through every active
 * recurrence whose lead-time window has opened
 * (`next_occurrence_at - lead_time_days <= now`, and whose `end_at`, if any,
 * has not yet passed the next occurrence) and materializes the due
 * occurrence into a real intervention draft.
 *
 * For each due recurrence:
 *
 * 1. The occurrence is idempotently reserved first
 *    ({@see InterventionRecurrencePort::reserveRun()}), so a Messenger retry
 *    or an overlapping sweep tick can never materialize the same occurrence
 *    twice — a reservation miss (already claimed) skips the recurrence
 *    entirely.
 * 2. The draft is created through {@see InterventionTemplateInstantiator} —
 *    the same core the API's template instantiation endpoint uses — with
 *    `origin: 'intervention:recurrence'` and a system actor (`actorUserId:
 *    null`). The recurrence's own `siteId`/`responsibleId` act as overrides
 *    over the template's defaults; `plannedStartAt` is the occurrence
 *    instant itself.
 * 3. On success, the run is marked `succeeded` with the created intervention
 *    id, `next_occurrence_at` advances to `rule->nextAfter(occurrence)`, and
 *    `last_materialized_at` is set.
 * 4. On failure (site archived, template deleted, ...), the run is marked
 *    `failed` with the error, `next_occurrence_at` still advances (no
 *    infinite retry on a permanently broken occurrence), and the
 *    recurrence's responsible (or the organization's administrators as a
 *    fallback) is notified best-effort.
 *
 * Every page is processed independently to keep memory bounded regardless
 * of how many organizations/recurrences exist.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MaterializeDueRecurrencesHandler implements CommandHandler
{
  // #region Constants
  /**
   * Page size used for the sweep, keeping every batch bounded in memory.
   */
  private const int PAGE_SIZE = 200;

  /**
   * The origin label recorded on drafts created by the recurring
   * materializer.
   */
  private const string ORIGIN = 'intervention:recurrence';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePort $recurrences the recurrences port
   * @param InterventionTemplateInstantiator $instantiator the shared template instantiation service
   * @param InterventionRecurrenceNotifier $notifier the recurrence failure notifier
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private InterventionTemplateInstantiator $instantiator,
    private InterventionRecurrenceNotifier $notifier,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param MaterializeDueRecurrencesCommand $command the command value
   *
   * @return VoidResult the command result
   */
  public function __invoke(MaterializeDueRecurrencesCommand $command): VoidResult
  {
    $now = $this->clock->now();
    $offset = 0;

    do {
      $page = $this->recurrences->pageDueForMaterialization($now, self::PAGE_SIZE, $offset);

      foreach ($page->items as $recurrence) {
        $this->materializeOne($recurrence);
      }

      $offset += self::PAGE_SIZE;
    } while (self::PAGE_SIZE === count($page->items));

    return new VoidResult();
  }

  /**
   * Method materializeOne.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the due recurrence view
   */
  private function materializeOne(InterventionRecurrenceView $recurrence): void
  {
    $occurrenceAt = $recurrence->nextOccurrenceAt;
    $runId = $this->recurrences->reserveRun($recurrence->id, $occurrenceAt);
    if (null === $runId) {
      // Already claimed by a previous tick or a concurrent worker: skip.
      return;
    }

    try {
      $draft = $this->instantiator->instantiate(
        templateId: $recurrence->templateId,
        origin: self::ORIGIN,
        name: $recurrence->name,
        siteId: $recurrence->siteId,
        responsibleId: $recurrence->responsibleId,
        plannedStartAt: $occurrenceAt,
        actorUserId: null,
      );
    } catch (Throwable $exception) {
      $this->handleFailure($recurrence, $runId, $occurrenceAt, $exception->getMessage());

      return;
    }

    $this->handleSuccess($recurrence, $runId, $occurrenceAt, $draft->interventionId);
  }

  /**
   * Method handleSuccess.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the recurrence view
   * @param string $runId the reserved run id
   * @param DateTimeImmutable $occurrenceAt the materialized occurrence instant
   * @param string $interventionId the created intervention id
   */
  private function handleSuccess(InterventionRecurrenceView $recurrence, string $runId, DateTimeImmutable $occurrenceAt, string $interventionId): void
  {
    $this->recurrences->markRunSucceeded($runId, $interventionId);

    $nextOccurrenceAt = $this->rule($recurrence)->nextAfter($occurrenceAt, $recurrence->timezone);
    $this->recurrences->advanceNextOccurrence($recurrence->id, $nextOccurrenceAt, $this->clock->now());

    $this->eventDispatcher->dispatch(new InterventionRecurrenceMaterializedEvent(
      organizationId: $recurrence->organizationId,
      recurrenceId: $recurrence->id,
      succeeded: true,
      interventionId: $interventionId,
    ));
  }

  /**
   * Method handleFailure.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the recurrence view
   * @param string $runId the reserved run id
   * @param DateTimeImmutable $occurrenceAt the failed occurrence instant
   * @param string $error the failure reason
   */
  private function handleFailure(InterventionRecurrenceView $recurrence, string $runId, DateTimeImmutable $occurrenceAt, string $error): void
  {
    $this->recurrences->markRunFailed($runId, $error);

    // No infinite retry: the occurrence advances even on failure.
    $nextOccurrenceAt = $this->rule($recurrence)->nextAfter($occurrenceAt, $recurrence->timezone);
    $this->recurrences->advanceNextOccurrence($recurrence->id, $nextOccurrenceAt, null);

    $this->notifier->notifyMaterializationFailed(
      $recurrence->organizationId,
      $recurrence->id,
      $recurrence->templateId,
      $recurrence->responsibleId,
      $error,
    );

    $this->eventDispatcher->dispatch(new InterventionRecurrenceMaterializedEvent(
      organizationId: $recurrence->organizationId,
      recurrenceId: $recurrence->id,
      succeeded: false,
      error: $error,
    ));
  }

  /**
   * Method rule.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $recurrence the recurrence view
   *
   * @return RecurrenceRule the rule reconstructed from the recurrence's stored fields
   */
  private function rule(InterventionRecurrenceView $recurrence): RecurrenceRule
  {
    return RecurrenceRule::fromValues($recurrence->frequency, $recurrence->interval, $recurrence->anchorDate);
  }
  // #endregion
}
