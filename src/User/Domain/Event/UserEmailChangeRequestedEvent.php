<?php

declare(strict_types=1);

namespace User\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event UserEmailChangeRequestedEvent.
 *
 * Raised when an authenticated user requests to change their sign-in
 * email address. The change is not applied until the confirmation
 * token sent to the new address is used.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class UserEmailChangeRequestedEvent implements DomainEvent
{
  /**
   * Constructor.
   *
   * @param Uuid $eventId the unique event identifier
   * @param string $userId the user ID
   * @param string $currentEmail the current (old) email address
   * @param string $newEmail the requested new email address
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private Uuid $eventId,
    public string $userId,
    public string $currentEmail,
    public string $newEmail,
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
      'current_email' => $this->currentEmail,
      'new_email' => $this->newEmail,
    ];
  }
}
