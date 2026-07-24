<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\Totp\ConfirmTotp;

use Shared\Application\Message\CommandMessage;

/**
 * Command ConfirmTotpCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ConfirmTotpCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $code the TOTP code to confirm the pending secret with
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $code,
  ) {
  }
  // #endregion
}
