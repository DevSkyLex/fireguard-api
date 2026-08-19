<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

use function array_filter;
use function array_map;
use function array_values;
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

  /**
   * Method closedValues.
   *
   * The single source of truth for "closed/terminal" — every case where
   * {@see self::isMutable()} is `false` (currently `published` and
   * `abandoned`). This is the exact population an intervention must fall
   * outside of to ever be "overdue": the statistics snapshot
   * (`GetInterventionStatisticsHandler`) and the `due=overdue` list filter
   * (`ListInterventionWorkflowHandler`) both exclude this set rather than
   * each hand-listing the two literals again.
   *
   * @since 1.1.0
   *
   * @return list<string> the closed status literals
   */
  public static function closedValues(): array
  {
    return array_values(array_map(
      static fn (self $status): string => $status->value,
      array_filter(self::cases(), static fn (self $status): bool => !$status->isMutable()),
    ));
  }
}
