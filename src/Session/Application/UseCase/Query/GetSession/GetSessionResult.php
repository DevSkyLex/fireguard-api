<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\GetSession;

use DateTimeImmutable;

/**
 * Result GetSessionResult
 * @final
 *
 * Result of getting a session.
 *
 * @category Result
 * @package Session\Application\UseCase\Query\GetSession
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionResult
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $sessionId The session ID.
   * @param string $userId The user ID.
   * @param string $ipAddress The IP address.
   * @param string $userAgent The user agent.
   * @param DateTimeImmutable $createdAt The creation timestamp.
   * @param DateTimeImmutable $lastActivityAt The last activity timestamp.
   * @param bool $isRevoked Whether the session is revoked.
   */
  public function __construct(
    public string $sessionId,
    public string $userId,
    public string $ipAddress,
    public string $userAgent,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $lastActivityAt,
    public bool $isRevoked,
  ) {
  }
  //#endregion
}
