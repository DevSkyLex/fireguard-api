<?php

declare(strict_types=1);

namespace User\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event UserCreatedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserCreatedEvent implements DomainEvent
{
  /**
   * Constructor.
   *
   * @param Uuid              $eventId    the unique event identifier
   * @param string            $userId     the user ID
   * @param string            $username   the username
   * @param string            $email      the user email
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private Uuid $eventId,
    public string $userId,
    public string $username,
    public string $email,
    public DateTimeImmutable $occurredAt,
  ) {
  }

  /**
   * Method eventId.
   *
   * Returns the unique event ID.
   *
   * @since 1.0.0
   *
   * @return Uuid the event ID
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt.
   *
   * Returns the timestamp when the event occurred.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the occurrence timestamp
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId.
   *
   * Returns the ID of the aggregate that raised the event.
   *
   * @since 1.0.0
   *
   * @return string the aggregate ID
   */
  public function aggregateId(): string
  {
    return $this->userId;
  }

  /**
   * Method aggregateType.
   *
   * Returns the type of the aggregate that raised the event.
   *
   * @since 1.0.0
   *
   * @return string the aggregate type
   */
  public function aggregateType(): string
  {
    return 'user';
  }

  /**
   * Method payload.
   *
   * Returns the event payload data.
   *
   * @since 1.0.0
   *
   * @return array<string, mixed> the event payload
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
