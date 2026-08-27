<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

/**
 * Exception InspectionResponseNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class InspectionResponseNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method notFound.
   *
   * Creates an exception for a missing inspection response.
   *
   * The identifier is deliberately NOT interpolated: this message is the
   * published 404 body of every `/inspection-responses/{id}` mutation, and
   * echoing an id the caller may not be entitled to see would widen it.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function notFound(): self
  {
    return new self('Inspection response not found.');
  }
  // #endregion
}
