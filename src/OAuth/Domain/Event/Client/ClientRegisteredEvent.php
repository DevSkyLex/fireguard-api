<?php

declare(strict_types=1);

namespace OAuth\Domain\Event\Client;

use DateTimeImmutable;
use OAuth\Domain\ValueObject\Client\ClientId;
use OAuth\Domain\ValueObject\Client\ClientName;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event ClientRegisteredEvent.
 *
 * @category Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ClientRegisteredEvent implements DomainEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance
   *  of the ClientRegisteredEvent class.
   *
   * @since 1.0.0
   *
   * @param Uuid $eventId the unique event identifier
   * @param ClientId $clientId the client ID
   * @param ClientName $name the client name
   * @param DateTimeImmutable $occurredAt when the event occurred
   */
  public function __construct(
    private readonly Uuid $eventId,
    public readonly ClientId $clientId,
    public readonly ClientName $name,
    public readonly DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method eventId
   * {@inheritDoc}
   *
   * Returns the event ID.
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
   * Method occurredAt
   * {@inheritDoc}
   *
   * Returns when the event occurred.
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
   * Method aggregateId
   * {@inheritDoc}
   *
   * Returns the aggregate ID.
   *
   * @since 1.0.0
   *
   * @return string the aggregate ID
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
   * @since 1.0.0
   *
   * @return string the aggregate type
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
   * @since 1.0.0
   *
   * @return array<string, mixed> the event payload
   */
  public function payload(): array
  {
    return [
      'client_id' => $this->clientId->value,
      'name' => $this->name->value,
    ];
  }
  // #endregion
}
