<?php

declare(strict_types=1);

namespace Mission\Domain\Service;

use Mission\Domain\Exception\MissionConflictException;
use Mission\Domain\ValueObject\MissionStatus;

use function in_array;
use function sprintf;

/**
 * Service MissionTransitionPolicy.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class MissionTransitionPolicy
{
  /**
   * Method assertAllowed.
   *
   * Executes the assert allowed operation.
   *
   * @since 1.0.0
   *
   * @param MissionStatus $from the from value
   * @param MissionStatus $to the to value
   */
  public function assertAllowed(MissionStatus $from, MissionStatus $to): void
  {
    if ($from === $to) {
      return;
    }

    $allowed = match ($from) {
      MissionStatus::DRAFT => [MissionStatus::PLANNED, MissionStatus::ABANDONED],
      MissionStatus::PLANNED => [MissionStatus::IN_PROGRESS, MissionStatus::ABANDONED],
      MissionStatus::IN_PROGRESS => [MissionStatus::SUBMITTED, MissionStatus::ABANDONED],
      MissionStatus::SUBMITTED => [MissionStatus::CHANGES_REQUESTED],
      MissionStatus::CHANGES_REQUESTED => [MissionStatus::IN_PROGRESS, MissionStatus::SUBMITTED, MissionStatus::ABANDONED],
      MissionStatus::PUBLISHED, MissionStatus::ABANDONED => [],
    };

    if (!in_array($to, $allowed, true)) {
      throw new MissionConflictException(sprintf('Mission cannot transition from %s to %s.', $from->value, $to->value));
    }
  }
}
