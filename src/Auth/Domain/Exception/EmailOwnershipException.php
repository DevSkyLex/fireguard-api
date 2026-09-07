<?php

declare(strict_types=1);

namespace Auth\Domain\Exception;

use RuntimeException;

/**
 * Exception EmailOwnershipException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EmailOwnershipException extends RuntimeException
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param string $errorCode stable neutral challenge failure code
   */
  public function __construct(public readonly string $errorCode = 'email_ownership_invalid_challenge')
  {
    parent::__construct($errorCode);
  }
  // #endregion
}
