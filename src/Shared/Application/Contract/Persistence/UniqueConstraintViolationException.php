<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Persistence;

use RuntimeException;
use Throwable;

/**
 * Class UniqueConstraintViolationException
 *
 * Exposes a uniqueness conflict without leaking a database-driver type into Application.
 * The original message and exception chain retain constraint diagnostics for owner-specific mapping.
 *
 * @category Application Contract
 */
final class UniqueConstraintViolationException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Retains the adapter failure and its database constraint diagnostic.
   *
   * @access public
   *
   * @param Throwable $previous the original uniqueness failure
   *
   * @return void
   */
  public function __construct(Throwable $previous)
  {
    parent::__construct($previous->getMessage(), 0, $previous);
  }
  // #endregion
}
