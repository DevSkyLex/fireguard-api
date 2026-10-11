<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use RuntimeException;

/**
 * Class RevisionMismatchException.
 *
 * Reports a malformed or stale conditional revision independently of HTTP translation.
 *
 * @category Exception
 */
final class RevisionMismatchException extends RuntimeException
{
  // #region Properties
  /**
   * Constant MESSAGE.
   *
   * Stable refusal shared by parsing and optimistic comparison.
   */
  public const string MESSAGE = 'The resource revision is stale.';
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   */
  public function __construct()
  {
    parent::__construct(self::MESSAGE);
  }
  // #endregion
}
