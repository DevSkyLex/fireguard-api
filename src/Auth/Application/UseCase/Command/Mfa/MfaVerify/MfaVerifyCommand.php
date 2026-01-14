<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaVerify;

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
   * @param string|null $ipAddress the client IP address
   * @param string|null $userAgent the client user agent
   * @param bool $rememberMe whether the session is persistent
   */
  public function __construct(
    public readonly string $preAuthToken,
    public readonly string $code,
    public readonly ?string $ipAddress = null,
    public readonly ?string $userAgent = null,
    public readonly bool $rememberMe = true,
  ) {
  }
  // #endregion
}
