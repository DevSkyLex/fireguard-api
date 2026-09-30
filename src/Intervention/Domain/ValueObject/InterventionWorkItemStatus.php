<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use function in_array;

/**
 * Enum InterventionWorkItemStatus.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionWorkItemStatus: string
{
  /**
   * Case PLANNED
   */
  case PLANNED = 'planned';

  /**
   * Case IN_PROGRESS
   */
  case IN_PROGRESS = 'in_progress';

  /**
   * Case COMPLETED
   */
  case COMPLETED = 'completed';

  /**
   * Case SKIPPED
   */
  case SKIPPED = 'skipped';

  /**
   * Method requiresSkipReason.
   *
   * A skipped work item must carry a non-empty reason — the fact alone does
   * not say why the required action was not performed.
   *
   * @since 1.0.0
   *
   * @return bool the requires skip reason result
   */
  public function requiresSkipReason(): bool
  {
    return in_array($this, [self::SKIPPED], true);
  }
}
