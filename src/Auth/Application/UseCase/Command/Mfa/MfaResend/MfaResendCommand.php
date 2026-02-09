<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Mfa\MfaResend;

use Shared\Application\Message\CommandMessage;

/**
 * Command MfaResendCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaResendCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * MfaResendCommand class.
   *
   * @since 1.0.0
   *
   * @param string $preAuthToken the pre-auth JWT token from login
   * @param string|null $ipAddress the client IP address
   * @param string|null $userAgent the client user agent
   */
  public function __construct(
    public readonly string $preAuthToken,
    public readonly ?string $ipAddress = null,
    public readonly ?string $userAgent = null,
  ) {
  }
  // #endregion
}
