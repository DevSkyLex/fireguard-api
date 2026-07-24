<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\Registration\ResendRegistration;

use Shared\Application\Message\CommandMessage;

/**
 * Command ResendRegistrationCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ResendRegistrationCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * ResendRegistrationCommand class.
   *
   * @since 1.0.0
   *
   * @param string $token the challenge token from the registration step
   */
  public function __construct(
    public readonly string $token,
  ) {
  }
  // #endregion
}
