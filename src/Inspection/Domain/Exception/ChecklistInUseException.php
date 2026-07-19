<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception ChecklistInUseException.
 *
 * Raised when an update attempts to change the item list of a checklist
 * that is already referenced by at least one existing inspection. Items
 * must stay structurally immutable once used so that historical inspection
 * evidence is never reinterpreted retroactively (regulated fire-safety
 * data). Only the name and reference code remain editable in that case.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class ChecklistInUseException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a checklist whose items cannot be changed
   * because it is already referenced by existing inspections.
   *
   * @since 1.0.0
   *
   * @param string $id the checklist identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf(
      'Checklist with ID "%s" is referenced by existing inspections; its items cannot be changed. Create a new checklist instead.',
      $id,
    ));
  }
  // #endregion
}
