<?php

declare(strict_types=1);

namespace Session\Application\UseCase\Query\GetSession;

use DateTimeImmutable;

/**
 * Result GetSessionResult.
 *
 * @category Result
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetSessionResult
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $sessionId the session ID
   * @param string $userId the user ID
   * @param string $ipAddress the IP address
   * @param string $userAgent the user agent
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param DateTimeImmutable $lastActivityAt the last activity timestamp
   * @param bool $isRevoked whether the session is revoked
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
  // #endregion
}
