<?php

declare(strict_types=1);

namespace Auth\Application\Contract\Event\Session;

use DateTimeImmutable;

/**
 * Event UserLoggedInEvent.
 *
 * @category Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserLoggedInEvent
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
   * UserLoggedInEvent class.
   *
   * @since 1.0.0
   *
   * @param string $userId the user ID
   * @param string $email the user email
   * @param string|null $ipAddress the client IP address
   * @param ?DateTimeImmutable $occurredAt persisted occurrence time, or null for a new event
   */
  public function __construct(
    public string $userId,
    public string $email,
    public ?string $ipAddress = null,
    ?DateTimeImmutable $occurredAt = null,
  ) {
    $this->occurredAt = $occurredAt ?? new DateTimeImmutable();
  }
  // #endregion
}
