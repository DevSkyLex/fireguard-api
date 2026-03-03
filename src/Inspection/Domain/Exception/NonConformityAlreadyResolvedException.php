<?php

declare(strict_types=1);

namespace Inspection\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception NonConformityAlreadyResolvedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class NonConformityAlreadyResolvedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for an already resolved non-conformity.
   *
   * @since 1.0.0
   *
   * @param string $id the non-conformity identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Non-conformity with ID "%s" is already resolved.', $id));
  }
  // #endregion
}
