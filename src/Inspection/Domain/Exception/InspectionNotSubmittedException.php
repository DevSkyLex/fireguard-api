<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Class InspectionNotSubmittedException
 *
 * Signals that an inspection must be submitted before it can be closed.
 *
 * @category Exception
 */
final class InspectionNotSubmittedException extends RuntimeException
{
  /**
   * Method withId.
   *
   * Creates the exception for an inspection that has not been submitted.
   *
   * @access public
   *
   * @static
   *
   * @param string $id the inspection identifier
   *
   * @return self the not-submitted exception
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Inspection with ID "%s" must be submitted before it can be closed.', $id));
  }
}
