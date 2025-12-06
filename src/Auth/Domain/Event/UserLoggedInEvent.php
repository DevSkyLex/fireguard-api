<?php

declare(strict_types=1);

namespace Auth\Domain\Event;

use DateTimeImmutable;

/**
 * Event UserLoggedInEvent
 * @final
 *
 * Domain event raised when a user successfully logs in.
 *
 * @category Event
 * @package Auth\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserLoggedInEvent
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
   * UserLoggedInEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $userId The user ID.
   * @param string $email The user email.
   * @param string|null $ipAddress The client IP address.
   */
  public function __construct(
    public string $userId,
    public string $email,
    public ?string $ipAddress = null,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  //#endregion
}
