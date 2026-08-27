<?php

declare(strict_types=1);

namespace Intervention\Domain\Service;

use Intervention\Domain\Exception\InterventionConflictException;
use Intervention\Domain\ValueObject\InterventionStatus;

use function in_array;
use function sprintf;

/**
 * Service InterventionTransitionPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InterventionTransitionPolicy
{
  /**
   * Method assertAllowed.
   *
   * Executes the assert allowed operation.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $from the from value
   * @param InterventionStatus $to the to value
   */
  public function assertAllowed(InterventionStatus $from, InterventionStatus $to): void
  {
    if ($from === $to) {
      return;
    }

    if (!in_array($to, $this->allowedFrom($from), true)) {
      throw new InterventionConflictException(sprintf('Intervention cannot transition from %s to %s.', $from->value, $to->value));
    }
  }

  /**
   * Method allowedFrom.
   *
   * Lists the workflow-legal next statuses from the given status. `PUBLISHED`
   * is never included: it is reached only through the publication flow, not a
   * direct transition. This is the single source of truth for the state machine
   * and is exposed to the client so it does not duplicate the transition table.
   *
   * @since 1.0.0
   *
   * @param InterventionStatus $from the from value
   *
   * @return list<InterventionStatus> the allowed next statuses
   */
  public function allowedFrom(InterventionStatus $from): array
  {
    return match ($from) {
      InterventionStatus::DRAFT => [InterventionStatus::PLANNED, InterventionStatus::ABANDONED],
      InterventionStatus::PLANNED => [InterventionStatus::IN_PROGRESS, InterventionStatus::ABANDONED],
      InterventionStatus::IN_PROGRESS => [InterventionStatus::SUBMITTED, InterventionStatus::ABANDONED],
      InterventionStatus::SUBMITTED => [InterventionStatus::CHANGES_REQUESTED, InterventionStatus::IN_PROGRESS],
      InterventionStatus::CHANGES_REQUESTED => [InterventionStatus::IN_PROGRESS, InterventionStatus::SUBMITTED, InterventionStatus::ABANDONED],
      InterventionStatus::PUBLISHED, InterventionStatus::ABANDONED => [],
    };
  }
}
