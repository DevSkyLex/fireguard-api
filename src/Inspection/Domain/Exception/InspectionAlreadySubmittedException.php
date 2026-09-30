<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Class InspectionAlreadySubmittedException
 *
 * Signals the InspectionAlreadySubmittedException failure condition.
 *
 * @category Exception
 */
final class InspectionAlreadySubmittedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId
   *
   * Creates the exception for an inspection that has already been submitted.
   *
   * @access public
   *
   * @param string $id the identifier
   *
   * @return self the created instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection with ID "%s" is already submitted.', $id));
  }
  // #endregion
}
