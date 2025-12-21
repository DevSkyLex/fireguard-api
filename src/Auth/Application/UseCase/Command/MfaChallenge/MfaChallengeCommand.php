<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaChallenge;

use Shared\Application\Message\CommandMessage;

/**
 * Command MfaChallengeCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * MfaChallengeCommand class.
   *
   * @since 1.0.0
   *
   * @param string   $userId     the user identifier
   * @param string   $purpose    The challenge purpose (e.g., 'login').
   * @param string   $channel    the delivery channel ('email', 'sms', 'totp')
   * @param string   $recipient  the recipient address (email or phone)
   * @param int|null $ttlSeconds optional custom TTL in seconds
   */
  public function __construct(
    public readonly string $userId,
    public readonly string $purpose,
    public readonly string $channel,
    public readonly string $recipient,
    public readonly ?int $ttlSeconds = null,
  ) {
  }
  // #endregion
}
