<?php

declare(strict_types=1);

namespace User\Domain\Exception;

use RuntimeException;

/**
 * Exception EmailOwnershipUnavailableException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipUnavailableException extends RuntimeException
{
  // #region Methods
  /**
   * Returns a neutral failure for unavailable accounts or an address changed during verification.
   *
   * @since 1.0.0
   *
   * @return self the denial
   */
  public static function unavailable(): self
  {
    return new self('email_ownership_unavailable');
  }
  // #endregion
}
