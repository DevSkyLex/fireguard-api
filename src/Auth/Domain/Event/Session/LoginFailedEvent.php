<?php

declare(strict_types=1);

namespace Auth\Domain\Event\Session;

use DateTimeImmutable;

/**
 * Event LoginFailedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class LoginFailedEvent
{
  // #region Properties
  /**
   * Property occurredAt.
   *
   * The date and time the event occurred.
   *
   * @since 1.0.0
   */
  public DateTimeImmutable $occurredAt;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the event with the provided values.
   *
   * @since 1.0.0
   *
   * @param string $email the email used in the attempt
   * @param string|null $ipAddress the IP address
   * @param string $reason the failure reason
   */
  public function __construct(
    public string $email,
    public ?string $ipAddress,
    public string $reason,
  ) {
    $this->occurredAt = new DateTimeImmutable();
  }
  // #endregion
}
