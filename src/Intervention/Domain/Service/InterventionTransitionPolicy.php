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

    $allowed = match ($from) {
      InterventionStatus::DRAFT => [InterventionStatus::PLANNED, InterventionStatus::ABANDONED],
      InterventionStatus::PLANNED => [InterventionStatus::IN_PROGRESS, InterventionStatus::ABANDONED],
      InterventionStatus::IN_PROGRESS => [InterventionStatus::SUBMITTED, InterventionStatus::ABANDONED],
      InterventionStatus::SUBMITTED => [InterventionStatus::CHANGES_REQUESTED],
      InterventionStatus::CHANGES_REQUESTED => [InterventionStatus::IN_PROGRESS, InterventionStatus::SUBMITTED, InterventionStatus::ABANDONED],
      InterventionStatus::PUBLISHED, InterventionStatus::ABANDONED => [],
    };

    if (!in_array($to, $allowed, true)) {
      throw new InterventionConflictException(sprintf('Intervention cannot transition from %s to %s.', $from->value, $to->value));
    }
  }
}
