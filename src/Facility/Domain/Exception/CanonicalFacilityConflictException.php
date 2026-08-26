<?php

declare(strict_types=1);

namespace Facility\Domain\Exception;

use RuntimeException;

/**
 * Exception CanonicalFacilityConflictException.
 *
 * The canonical DELETE refusing to hard-delete a scratchpad row that still
 * has children. Mapped to 409.
 *
 * A hard delete would set every child's `parent_facility_id` to NULL
 * (`ON DELETE SET NULL`), silently promoting the sub-tree to root — a data
 * loss no response would mention.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CanonicalFacilityConflictException extends RuntimeException
{
  // #region Methods
  /**
   * Method stillHasChildren.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function stillHasChildren(): self
  {
    return new self('Cannot delete a facility that still has child facilities; move or remove them first.');
  }
  // #endregion
}
