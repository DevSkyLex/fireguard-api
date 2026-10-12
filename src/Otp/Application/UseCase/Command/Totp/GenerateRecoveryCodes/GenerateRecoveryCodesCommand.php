<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\GenerateRecoveryCodes;

use Shared\Application\Message\CommandMessage;

/**
 * Class GenerateRecoveryCodesCommand
 *
 * Requests replacement of the recovery lot with current authenticator possession proof.
 *
 * @category Command
 */
final readonly class GenerateRecoveryCodesCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $userId authenticated enrollment owner
   * @param string $code current TOTP code
   *
   * @return void
   */
  public function __construct(public string $userId, public string $code)
  {
  }
  // #endregion
}
