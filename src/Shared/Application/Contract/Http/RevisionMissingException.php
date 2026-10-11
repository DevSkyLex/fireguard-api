<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Http;

use RuntimeException;

/**
 * Class RevisionMissingException.
 *
 * Reports an absent conditional revision without depending on an HTTP framework.
 *
 * @category Exception
 */
final class RevisionMissingException extends RuntimeException
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @access public
   */
  public function __construct()
  {
    parent::__construct('If-Match is required for this mutation.');
  }
  // #endregion
}
