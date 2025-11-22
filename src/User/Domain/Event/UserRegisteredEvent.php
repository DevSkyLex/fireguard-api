<?php

declare(strict_types=1);

namespace User\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event UserRegisteredEvent
 * @final
 *
 * Emitted when a new user is registered.
 *
 * @category Event
 * @package User\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserRegisteredEvent implements DomainEvent
{
  private Uuid $eventId;

  /**
   * Constructor
   *
   * @param string $userId The user ID.
   * @param string $username The username.
   * @param string $email The user email.
   * @param DateTimeImmutable $occurredAt When the event occurred.
   */
  public function __construct(
    public string $userId,
    public string $username,
    public string $email,
    public DateTimeImmutable $occurredAt,
  ) {
    $this->eventId = Uuid::generate();
  }

  /**
   * Method eventId
   *
   * Returns the unique event ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return Uuid The event ID.
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   *
   * Returns the timestamp when the event occurred.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The occurrence timestamp.
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   *
   * Returns the ID of the aggregate that raised the event.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The aggregate ID.
   */
  public function aggregateId(): string
  {
    return $this->userId;
  }

  /**
   * Method aggregateType
   *
   * Returns the type of the aggregate that raised the event.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The aggregate type.
   */
  public function aggregateType(): string
  {
    return 'user';
  }

  /**
   * Method payload
   *
   * Returns the event payload data.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The event payload.
   */
  public function payload(): array
  {
    return [
      'user_id' => $this->userId,
      'username' => $this->username,
      'email' => $this->email,
    ];
  }
}
