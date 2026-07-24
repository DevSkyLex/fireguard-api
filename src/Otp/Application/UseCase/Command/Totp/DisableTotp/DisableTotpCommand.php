<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\DisableTotp;

use Shared\Application\Message\CommandMessage;

/**
 * Command DisableTotpCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DisableTotpCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $code the current TOTP code, proving possession of the authenticator
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $code,
  ) {
  }
  // #endregion
}
