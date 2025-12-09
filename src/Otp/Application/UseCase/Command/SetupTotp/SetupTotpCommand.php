<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\SetupTotp;

use Shared\Application\Message\CommandMessage;

/**
 * Command SetupTotpCommand
 * @final
 *
 * Command to setup TOTP for a user.
 *
 * @category Command
 * @package Otp\Application\UseCase\Command\SetupTotp
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetupTotpCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * SetupTotpCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $accountName The account name/email for display.
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $accountName,
  ) {}
  //#endregion
}
