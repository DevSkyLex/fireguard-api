<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\RecoverReservedRecurrence;

use Intervention\Application\Port\Outbound\{InterventionRecurrencePort, InterventionWorkflowGatewayPort};
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceMaterializedEvent;
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\ValueObject\RecurrenceRule;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, TransactionManagerPort};

use function trim;

/**
 * Class RecoverReservedRecurrenceHandler
 *
 * Finalizes a legacy reservation without automatically duplicating an ambiguously created draft.
 *
 * @category UseCase
 */
final readonly class RecoverReservedRecurrenceHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param InterventionRecurrencePort $recurrences locks and finalizes the occurrence
   * @param InterventionWorkflowGatewayPort $workflow verifies linked intervention scope
   * @param TransactionManagerPort $transactions owns the main transaction
   * @param EventDispatcherPort $events persists the final outcome in the main outbox
   * @param ClockPort $clock supplies the recovery instant
   *
   * @return void
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private InterventionWorkflowGatewayPort $workflow,
    private TransactionManagerPort $transactions,
    private EventDispatcherPort $events,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Resolves exactly one pending legacy marker; replay emits no additional outcome.
   *
   * @access public
   *
   * @param RecoverReservedRecurrenceCommand $command explicit recovery decision
   *
   * @return VoidResult the completed command result
   */
  public function __invoke(RecoverReservedRecurrenceCommand $command): VoidResult
  {
    if ((null === $command->interventionId) === (null === $command->failureReason) || (null !== $command->failureReason && '' === trim($command->failureReason))) {
      throw new InterventionValidationException('Provide either a verified existing intervention or a non-empty failure reason.');
    }
    $this->transactions->transactional(function () use ($command): void {
      $recurrence = $this->recurrences->lockReservedRun($command->runId);
      if (null === $recurrence) {
        return;
      }
      if (null !== $command->interventionId) {
        $context = $this->workflow->interventionContext($command->interventionId);
        if (null === $context || $context->organizationId !== $recurrence->organizationId) {
          throw new InterventionValidationException('The linked intervention must belong to the recurrence organization.');
        }
        $this->recurrences->markRunSucceeded($command->runId, $command->interventionId);
      } else {
        $this->recurrences->markRunFailed($command->runId, $command->failureReason ?? 'Operator skipped legacy occurrence.');
      }
      $next = RecurrenceRule::fromValues($recurrence->frequency, $recurrence->interval, $recurrence->anchorDate)->nextAfter($recurrence->nextOccurrenceAt, $recurrence->timezone);
      $this->recurrences->advanceNextOccurrence($recurrence->id, $next, null !== $command->interventionId ? $this->clock->now() : null);
      $this->events->dispatch(new InterventionRecurrenceMaterializedEvent(
        organizationId: $recurrence->organizationId,
        recurrenceId: $recurrence->id,
        succeeded: null !== $command->interventionId,
        interventionId: $command->interventionId,
        error: $command->failureReason,
        templateId: $recurrence->templateId,
        responsibleId: $recurrence->responsibleId,
      ));
    });

    return new VoidResult();
  }
  // #endregion
}
