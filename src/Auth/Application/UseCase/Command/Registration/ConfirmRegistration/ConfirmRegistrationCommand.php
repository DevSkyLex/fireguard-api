<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ConfirmRegistration;

use Shared\Application\Message\CommandMessage;

/**
 * Command ConfirmRegistrationCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmRegistrationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ConfirmRegistrationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the challenge token from the registration step
   * @param string $code the OTP code received by email
   * @param string|null $ipAddress the client IP address
   * @param string|null $userAgent the client user agent
   */
  public function __construct(
    public readonly string $token,
    public readonly string $code,
    public readonly ?string $ipAddress = null,
    public readonly ?string $userAgent = null,
  ) {
  }
  // #endregion
}
