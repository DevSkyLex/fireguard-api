<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Sweep\MaterializeDueRecurrences;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\Service\InterventionTemplateInstantiator;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use Intervention\Domain\Exception\{InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Intervention\Domain\ValueObject\RecurrenceRule;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

use function count;

/**
 * UseCase MaterializeDueRecurrencesHandler.
 *
 * Materializes one occurrence per due recurrence using bounded id-keyset pages
 * and a fixed eligibility instant. Reservation, the complete draft, final run,
 * schedule advance and outbox outcome commit together on main. Transient
 * persistence/enqueue failures roll back and propagate for retry; explicit domain
 * failures record a terminal failed occurrence and durable failure notification.
 * Concurrent or completed occurrence claims are no-ops.
 *
 * @category UseCase
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
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   * @param ClockPort $clock the clock port
   * @param TransactionManagerPort $transactions owns each complete occurrence on main
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private InterventionTemplateInstantiator $instantiator,
    private EventDispatcherPort $eventDispatcher,
    private ClockPort $clock,
    private TransactionManagerPort $transactions,
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
    $afterId = null;

    do {
      $page = $this->recurrences->pageDueForMaterialization($now, self::PAGE_SIZE, $afterId);

      foreach ($page->items as $recurrence) {
        $this->materializeOne($recurrence);
        $afterId = $recurrence->id;
      }

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

    try {
      $this->transactions->transactional(function () use ($recurrence, $occurrenceAt): void {
        $runId = $this->recurrences->reserveRun($recurrence->id, $occurrenceAt);
        if (null === $runId) {
          return;
        }
        $draft = $this->instantiator->instantiate(
          templateId: $recurrence->templateId,
          origin: self::ORIGIN,
          name: $recurrence->name,
          siteId: $recurrence->siteId,
          responsibleId: $recurrence->responsibleId,
          plannedStartAt: $occurrenceAt,
          actorUserId: null,
        );
        $this->handleSuccess($recurrence, $runId, $occurrenceAt, $draft->interventionId);
      });
    } catch (InterventionNotFoundException|InterventionConflictException|InterventionValidationException $exception) {
      // Permanent input failure: the draft rolled back before recording the failed occurrence.
      $this->transactions->transactional(function () use ($recurrence, $occurrenceAt, $exception): void {
        $runId = $this->recurrences->reserveRun($recurrence->id, $occurrenceAt);
        if (null !== $runId) {
          $this->handleFailure($recurrence, $runId, $occurrenceAt, $exception->getMessage());
        }
      });
    }
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

    $this->eventDispatcher->dispatch(new InterventionRecurrenceMaterializedEvent(
      organizationId: $recurrence->organizationId,
      recurrenceId: $recurrence->id,
      succeeded: false,
      error: $error,
      templateId: $recurrence->templateId,
      responsibleId: $recurrence->responsibleId,
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
