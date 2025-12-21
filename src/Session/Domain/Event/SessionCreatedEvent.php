<?php

declare(strict_types=1);

namespace Session\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event SessionCreatedEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionCreatedEvent implements DomainEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param Uuid $eventId the event ID
   * @param string $sessionId the session ID
   * @param string $userId the user ID
   * @param string $ipAddress the client IP address
   * @param string $userAgent the client user agent
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private Uuid $eventId,
    private string $sessionId,
    private string $userId,
    private string $ipAddress,
    private string $userAgent,
    private DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
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
      'ip_address' => $this->ipAddress,
      'user_agent' => $this->userAgent,
    ];
  }

  /**
   * Method sessionId.
   *
   * Returns the session ID.
   *
   * @since 1.0.0
   *
   * @return string the session ID
   */
  public function sessionId(): string
  {
    return $this->sessionId;
  }

  /**
   * Method userId.
   *
   * Returns the user ID.
   *
   * @since 1.0.0
   *
   * @return string the user ID
   */
  public function userId(): string
  {
    return $this->userId;
  }

  /**
   * Method ipAddress.
   *
   * Returns the IP address.
   *
   * @since 1.0.0
   *
   * @return string the IP address
   */
  public function ipAddress(): string
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent.
   *
   * Returns the user agent.
   *
   * @since 1.0.0
   *
   * @return string the user agent
   */
  public function userAgent(): string
  {
    return $this->userAgent;
  }
  // #endregion
}
