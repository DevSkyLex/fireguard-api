<?php

declare(strict_types=1);

namespace Shared\Application\Contract\Crypto;

use RuntimeException;

/**
 * Class CiphertextRejectedException
 *
 * Rejects unavailable keys, malformed envelopes and failed authentication without secret diagnostics.
 *
 * @category Exception
 */
final class CiphertextRejectedException extends RuntimeException
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @return void
   */
  public function __construct()
  {
    parent::__construct('Encrypted result authentication failed.');
  }
  // #endregion
}
