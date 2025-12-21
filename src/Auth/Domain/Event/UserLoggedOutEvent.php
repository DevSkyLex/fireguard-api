<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

use DateTimeImmutable;

/**
 * Event UserLoggedOutEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserLoggedOutEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the
   * UserLoggedOutEvent class.
   *
   * @since 1.0.0
   *
   * @param string|null $userId              the user ID (if known)
   * @param string|null $ipAddress           the client IP address
   * @param bool        $refreshTokenRevoked whether refresh token was revoked
   * @param bool        $accessTokenRevoked  whether access token was revoked
   */
  public function __construct(
    public ?string $userId = null,
    public ?string $ipAddress = null,
    public bool $refreshTokenRevoked = false,
    public bool $accessTokenRevoked = false,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
