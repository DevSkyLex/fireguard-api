<?php

declare(strict_types=1);

namespace Otp\Application\UseCase\Command\SetupTotp;

use Shared\Application\Message\CommandMessage;

/**
 * Command SetupTotpCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetupTotpCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * SetupTotpCommand class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $accountName the account name/email for display
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $accountName,
  ) {
  }
  // #endregion
}
