<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception InspectionAlreadyCancelledException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionAlreadyCancelledException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for an already cancelled inspection.
   *
   * @since 1.0.0
   *
   * @param string $id the inspection identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection with ID "%s" is already cancelled.', $id));
  }
  // #endregion
}
