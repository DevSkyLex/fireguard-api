<?php

declare(strict_types=1);

namespace Session\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event SessionRevokedEvent
 * @final
 *
 * Raised when a session is revoked.
 *
 * @category Event
 * @package Session\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionRevokedEvent implements DomainEvent
{
  //#region Constructor
  /**
   * Constructor
   *
   * @access public
   * @since 1.0.0
   *
   * @param Uuid $eventId The event ID.
   * @param string $sessionId The session ID.
   * @param string $userId The user ID.
   * @param string $reason The revocation reason.
   * @param DateTimeImmutable $occurredAt When the event occurred.
   */
  public function __construct(
    private Uuid $eventId,
    private string $sessionId,
    private string $userId,
    private string $reason,
    private DateTimeImmutable $occurredAt,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   */
  public function eventId(): Uuid
  {
    return $this->eventId;
  }

  /**
   * Method occurredAt
   * {@inheritDoc}
   */
  public function occurredAt(): DateTimeImmutable
  {
    return $this->occurredAt;
  }

  /**
   * Method aggregateId
   * {@inheritDoc}
   */
  public function aggregateId(): string
  {
    return $this->sessionId;
  }

  /**
   * Method aggregateType
   * {@inheritDoc}
   */
  public function aggregateType(): string
  {
    return 'Session';
  }

  /**
   * Method payload
   * {@inheritDoc}
   *
   * @return array<string, mixed>
   */
  public function payload(): array
  {
    return [
      'session_id' => $this->sessionId,
      'user_id' => $this->userId,
      'reason' => $this->reason,
    ];
  }

  /**
   * Method sessionId
   *
   * Returns the session ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The session ID.
   */
  public function sessionId(): string
  {
    return $this->sessionId;
  }

  /**
   * Method userId
   *
   * Returns the user ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user ID.
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method reason
   *
   * Returns the revocation reason.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The reason.
   */
  public function reason(): string
  {
    return $this->reason;
  }
  //#endregion
}
