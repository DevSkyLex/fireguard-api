<?php

declare(strict_types=1);

namespace Otp\Application\Exception;

use Shared\Application\Contract\Exception\ApplicationException;

/**
 * Class RecoveryCodesProofRejectedException
 *
 * Rejects generation without proving possession of the current authenticator.
 *
 * @category Exception
 */
final class RecoveryCodesProofRejectedException extends ApplicationException
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
    parent::__construct('Invalid verification code.');
  }
  // #endregion
}
