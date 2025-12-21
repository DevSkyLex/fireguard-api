<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaVerify;

use Shared\Application\Message\CommandMessage;

/**
 * Command MfaVerifyCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaVerifyCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the MfaVerifyCommand class.
   *
   * @since 1.0.0
   *
   * @param string $preAuthToken the pre-auth JWT token from initial login
   * @param string $code the verification code entered by the user
   */
  public function __construct(
    public readonly string $preAuthToken,
    public readonly string $code,
  ) {
  }
  // #endregion
}
