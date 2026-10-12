<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Query\Totp\GetRecoveryCodesStatus;

use Shared\Application\Message\QueryMessage;

/**
 * Class GetRecoveryCodesStatusQuery
 *
 * Requests only recovery availability metadata for the authenticated owner.
 *
 * @category Query
 */
final readonly class GetRecoveryCodesStatusQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId authenticated owner
   *
   * @return void
   */
  public function __construct(public string $userId)
  {
  }
  // #endregion
}
