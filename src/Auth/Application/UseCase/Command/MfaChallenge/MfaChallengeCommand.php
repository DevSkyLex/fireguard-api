<?php

declare(strict_types=1);

namespace Auth\Application\UseCase\Command\MfaChallenge;

use Shared\Application\Message\CommandMessage;

/**
 * Command MfaChallengeCommand
 * @final
 *
 * Command to generate an MFA challenge during login.
 *
 * @category Command
 * @package Auth\Application\UseCase\Command\MfaChallenge
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MfaChallengeCommand implements CommandMessage
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the MfaChallengeCommand class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user identifier.
   * @param string $purpose The challenge purpose (e.g., 'login').
   * @param string $channel The delivery channel ('email', 'sms', 'totp').
   * @param string $recipient The recipient address (email or phone).
   * @param int|null $ttlSeconds Optional custom TTL in seconds.
   */
  public function __construct(
    public string $userId,
    public string $purpose,
    public string $channel,
    public string $recipient,
    public ?int $ttlSeconds = null,
  ) {
  }
  //#endregion
}
