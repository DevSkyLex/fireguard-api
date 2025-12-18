<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenRefreshFailedEvent
 * @final
 *
 * Raised when a token refresh attempt fails.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRefreshFailedEvent
{
  //#region Properties
  /**
   * Property occurredAt
   *
   * The timestamp when the event occurred.
   *
   * @access public
   * @since 1.0.0
   *
   * @var DateTimeImmutable
   */
  public DateTimeImmutable $occurredAt;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance
   * of the TokenRefreshFailedEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string|null $userId The user ID if known.
   * @param string|null $ipAddress The IP address.
   * @param string $reason The failure reason.
   */
  public function __construct(
    public readonly ?string $userId,
    public readonly ?string $ipAddress,
    public readonly string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
