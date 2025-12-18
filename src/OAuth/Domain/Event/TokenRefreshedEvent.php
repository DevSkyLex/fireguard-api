<?php

declare(strict_types=1);

namespace OAuth\Domain\Event;

use DateTimeImmutable;

/**
 * Event TokenRefreshedEvent
 * @final
 *
 * Raised when a token is successfully refreshed.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class TokenRefreshedEvent
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
   * of the TokenRefreshedEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string|null $ipAddress The IP address.
   */
  public function __construct(
    public readonly string $userId,
    public readonly ?string $ipAddress,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
