<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use function in_array;

/**
 * Enum InterventionStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionStatus: string
{
  case DRAFT = 'draft';
  case PLANNED = 'planned';
  case IN_PROGRESS = 'in_progress';
  case SUBMITTED = 'submitted';
  case CHANGES_REQUESTED = 'changes_requested';
  case PUBLISHED = 'published';
  case ABANDONED = 'abandoned';

  /**
   * Method isMutable.
   *
   * Executes the is mutable operation.
   *
   * @since 1.0.0
   *
   * @return bool the is mutable result
   */
  public function isMutable(): bool
  {
    return !in_array($this, [self::PUBLISHED, self::ABANDONED], true);
  }
}
