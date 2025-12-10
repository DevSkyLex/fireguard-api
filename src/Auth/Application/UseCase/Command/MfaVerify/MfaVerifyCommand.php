<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaVerify;

use Shared\Application\Message\CommandMessage;

/**
 * Command MfaVerifyCommand
 * @final
 *
 * Command to verify an MFA code and complete authentication.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\MfaVerify
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the MfaVerifyCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $preAuthToken The pre-auth JWT token from initial login.
   * @param string $code The verification code entered by the user.
   */
  public function __construct(
    public readonly string $preAuthToken,
    public readonly string $code,
  ) {}
  //#endregion
}
