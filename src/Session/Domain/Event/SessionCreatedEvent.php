<?php

declare(strict_types=1);

namespace Session\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event SessionCreatedEvent
 * @final
 *
 * Raised when a new session is created.
 *
 * @category Event
 * @package Session\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SessionCreatedEvent implements DomainEvent
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
   * @param string $ipAddress The client IP address.
   * @param string $userAgent The client user agent.
   * @param DateTimeImmutable $occurredAt When the event occurred.
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
      'ip_address' => $this->ipAddress,
      'user_agent' => $this->userAgent,
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
   * Method ipAddress
   *
   * Returns the IP address.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The IP address.
   */
  public function ipAddress(): string
  {
    return $this->ipAddress;
  }

  /**
   * Method userAgent
   *
   * Returns the user agent.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The user agent.
   */
  public function userAgent(): string
  {
    return $this->userAgent;
  }
  //#endregion
}
