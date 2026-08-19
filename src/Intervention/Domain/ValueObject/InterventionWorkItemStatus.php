<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use function in_array;

/**
 * Enum InterventionWorkItemStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionWorkItemStatus: string
{
  case PLANNED = 'planned';
  case IN_PROGRESS = 'in_progress';
  case COMPLETED = 'completed';
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
