<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Command\Session\CreateSession;

use Shared\Application\Message\CommandMessage;

/**
 * Command CreateSessionCommand.
 *
 * @category Command
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CreateSessionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the command with the user ID, IP address, user agent,
   * and optional session metadata.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $ipAddress the client IP address
   * @param string $userAgent the client user agent
   * @param string|null $accessTokenId the access token ID
   * @param string|null $refreshTokenId the refresh token ID
   * @param bool $rememberMe whether the session is persistent
   * @param array<string, mixed>|null $metadata optional session metadata
   */
  public function __construct(
    public string $userId,
    public string $ipAddress,
    public string $userAgent,
    public ?string $accessTokenId = null,
    public ?string $refreshTokenId = null,
    public bool $rememberMe = false,
    public ?array $metadata = null,
  ) {
  }
  // #endregion
}
