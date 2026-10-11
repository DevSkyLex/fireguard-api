<?php

declare(strict_types=1);

namespace Messaging\Domain\Exception;

use RuntimeException;

/**
 * Class MessagingRevisionPreconditionException
 *
 * Reports a missing or stale concurrency precondition without HTTP dependencies.
 *
 * @category Exception
 */
final class MessagingRevisionPreconditionException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Distinguishes a missing precondition from a stale or malformed value.
   *
   * @access public
   *
   * @param bool $missing whether the caller omitted the precondition
   *
   * @return void
   */
  public function __construct(public readonly bool $missing)
  {
    parent::__construct($missing ? 'If-Match is required for this mutation.' : 'The resource revision is stale.');
  }
  // #endregion
}
