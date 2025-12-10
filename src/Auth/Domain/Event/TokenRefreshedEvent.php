<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

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
   * @var DateTimeImmutable
   */
  public DateTimeImmutable $occurredAt;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * @param string $userId The user ID.
   * @param string|null $ipAddress The IP address.
   */
  public function __construct(
    public string $userId,
    public ?string $ipAddress,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
