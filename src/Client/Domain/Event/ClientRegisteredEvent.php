<?php

declare(strict_types=1);

namespace Client\Domain\Event;

use Client\Domain\ValueObject\ClientId;
use Client\Domain\ValueObject\ClientName;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;
use DateTimeImmutable;

/**
 * Event ClientRegisteredEvent
 * @final
 *
 * Raised when a new OAuth client is registered.
 *
 * @category Event
 * @package Client\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRegisteredEvent implements DomainEvent
{
  //#region Properties
  /**
   * Property eventId
   *
   * The unique identifier for this event.
   *
   * @access private
   * @since 1.0.0
   *
   * @var Uuid $eventId
   */
  private Uuid $eventId;
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the ClientRegisteredEvent class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param ClientId $clientId The client ID.
   * @param ClientName $name The client name.
   * @param DateTimeImmutable $occurredAt When the event occurred.
   */
  public function __construct(
    public ClientId $clientId,
    public ClientName $name,
    public DateTimeImmutable $occurredAt
  ) {
    $this->eventId = Uuid::generate();
  }
  //#endregion

  //#region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   *
   * Returns the event ID.
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
   * {@inheritDoc}
   *
   * Returns when the event occurred.
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
   * {@inheritDoc}
   *
   * Returns the aggregate ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The aggregate ID.
   */
  public function aggregateId(): string
  {
    return $this->clientId->value;
  }

  /**
   * Method aggregateType
   * {@inheritDoc}
   *
   * Returns the aggregate type.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The aggregate type.
   */
  public function aggregateType(): string
  {
    return 'client';
  }

  /**
   * Method payload
   * {@inheritDoc}
   *
   * Returns the event payload.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<string, mixed> The event payload.
   */
  public function payload(): array
  {
    return [
      'client_id' => $this->clientId->value,
      'name' => $this->name->value,
    ];
  }
  //#endregion
}
