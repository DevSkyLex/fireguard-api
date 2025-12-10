<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

use DateTimeImmutable;

/**
 * Event LoginFailedEvent
 * @final
 *
 * Raised when a login attempt fails.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginFailedEvent
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
   * @param string $email The email used in the attempt.
   * @param string|null $ipAddress The IP address.
   * @param string $reason The failure reason.
   */
  public function __construct(
    public string $email,
    public ?string $ipAddress,
    public string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
