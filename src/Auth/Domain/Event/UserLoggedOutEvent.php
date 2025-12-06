<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

use DateTimeImmutable;

/**
 * Event UserLoggedOutEvent
 * @final
 *
 * Domain event raised when a user logs out.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserLoggedOutEvent
{
  //#region Properties
  /**
   * Property occurredAt
   *
   * @var DateTimeImmutable
   */
  public DateTimeImmutable $occurredAt;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the
   * UserLoggedOutEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $userId The user ID (if known).
   * @param string|null $ipAddress The client IP address.
   * @param bool $refreshTokenRevoked Whether refresh token was revoked.
   * @param bool $accessTokenRevoked Whether access token was revoked.
   */
  public function __construct(
    public ?string $userId = null,
    public ?string $ipAddress = null,
    public bool $refreshTokenRevoked = false,
    public bool $accessTokenRevoked = false,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
