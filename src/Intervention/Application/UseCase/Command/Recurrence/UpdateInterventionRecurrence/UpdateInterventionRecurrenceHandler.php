<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Recurrence\UpdateInterventionRecurrence;

use DateTimeImmutable;
use Intervention\Application\Contract\Recurrence\InterventionRecurrenceView;
use Intervention\Application\Port\Outbound\InterventionRecurrencePort;
use Intervention\Application\UseCase\Command\Recurrence\CreateInterventionRecurrence\CreateInterventionRecurrenceHandler;
use Intervention\Domain\Event\Recurrence\InterventionRecurrenceUpdatedEvent;
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Intervention\Domain\ValueObject\RecurrenceRule;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * UseCase UpdateInterventionRecurrenceHandler.
 *
 * Applies a merge-patch to an existing recurrence. Whenever a rule-affecting
 * field changes (`frequency`, `interval`, `anchorDate`, `timezone`),
 * `next_occurrence_at` is recomputed against the new rule so a stale
 * schedule computed under the previous rule never survives the edit.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UpdateInterventionRecurrenceHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrencePort $recurrences the recurrences port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the event dispatcher port
   */
  public function __construct(
    private InterventionRecurrencePort $recurrences,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
    private EventDispatcherPort $eventDispatcher,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param UpdateInterventionRecurrenceCommand $command the command value
   *
   * @return UpdateInterventionRecurrenceResult the command result
   */
  public function __invoke(UpdateInterventionRecurrenceCommand $command): UpdateInterventionRecurrenceResult
  {
    $existing = $this->recurrences->find($command->recurrenceId);
    if (null === $existing) {
      throw InterventionNotFoundException::withId($command->recurrenceId);
    }
    if (!$this->authorization->hasPermission($command->userId, $existing->organizationId, 'organization.interventions.plan')) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.plan permission.');
    }

    $name = $command->hasName && null !== $command->name
      ? CreateInterventionRecurrenceHandler::validatedName($command->name)
      : null;
    $leadTimeDays = $command->hasLeadTimeDays && null !== $command->leadTimeDays
      ? CreateInterventionRecurrenceHandler::validatedLeadTimeDays($command->leadTimeDays)
      : null;
    $timezone = $command->hasTimezone && null !== $command->timezone
      ? RecurrenceRule::assertTimezone($command->timezone)->getName()
      : null;

    [$nextOccurrenceAt, $hasNextOccurrenceAt] = $this->recomputedNextOccurrence($existing, $command, $timezone);

    $view = $this->recurrences->update(
      id: $command->recurrenceId,
      name: $name,
      siteId: $command->siteId,
      responsibleId: $command->responsibleId,
      frequency: $command->hasFrequency ? $command->frequency : null,
      interval: $command->hasInterval ? $command->interval : null,
      anchorDate: $command->hasAnchorDate ? $command->anchorDate : null,
      timezone: $timezone,
      leadTimeDays: $leadTimeDays,
      nextOccurrenceAt: $nextOccurrenceAt,
      endAt: $command->endAt,
      isActive: $command->hasIsActive ? $command->isActive : null,
      hasName: $command->hasName,
      hasSiteId: $command->hasSiteId,
      hasResponsibleId: $command->hasResponsibleId,
      hasFrequency: $command->hasFrequency,
      hasInterval: $command->hasInterval,
      hasAnchorDate: $command->hasAnchorDate,
      hasTimezone: $command->hasTimezone,
      hasLeadTimeDays: $command->hasLeadTimeDays,
      hasNextOccurrenceAt: $hasNextOccurrenceAt,
      hasEndAt: $command->hasEndAt,
      hasIsActive: $command->hasIsActive,
    );

    $this->eventDispatcher->dispatch(new InterventionRecurrenceUpdatedEvent(
      organizationId: $existing->organizationId,
      recurrenceId: $view->id,
      actorUserId: $command->userId,
    ));

    return new UpdateInterventionRecurrenceResult($view);
  }

  /**
   * Method recomputedNextOccurrence.
   *
   * Recomputes `next_occurrence_at` against the merged rule whenever a
   * rule-affecting field (`frequency`, `interval`, `anchorDate`, `timezone`)
   * is present in the merge-patch request; otherwise the stored value is
   * left untouched.
   *
   * @since 1.0.0
   *
   * @param InterventionRecurrenceView $existing the existing recurrence view
   * @param UpdateInterventionRecurrenceCommand $command the command value
   * @param ?string $validatedTimezone the already-validated timezone override, if any
   *
   * @return array{0: ?DateTimeImmutable, 1: bool} the recomputed next occurrence and whether it changed
   */
  private function recomputedNextOccurrence(
    InterventionRecurrenceView $existing,
    UpdateInterventionRecurrenceCommand $command,
    ?string $validatedTimezone,
  ): array {
    $ruleChanged = $command->hasFrequency || $command->hasInterval || $command->hasAnchorDate || $command->hasTimezone;
    if (!$ruleChanged) {
      return [null, false];
    }

    $frequency = $command->hasFrequency && null !== $command->frequency ? $command->frequency : $existing->frequency;
    $interval = $command->hasInterval && null !== $command->interval ? $command->interval : $existing->interval;
    $anchorDate = $command->hasAnchorDate && null !== $command->anchorDate ? $command->anchorDate : $existing->anchorDate;
    $timezone = $validatedTimezone ?? $existing->timezone;

    $rule = RecurrenceRule::fromValues($frequency, $interval, $anchorDate);
    $nextOccurrenceAt = $rule->nextAfter($this->clock->now(), $timezone);

    return [$nextOccurrenceAt, true];
  }
  // #endregion
}
