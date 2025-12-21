<?php

declare(strict_types=1);

namespace Shared\Application\Event;

use DateTimeImmutable;
use Shared\Domain\Event\DomainEvent;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event ApplicationEvent.
 *
 * @category Application Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ApplicationEvent
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initialize the application event
   *
   * @param Uuid $eventId The event id
   * @param string $aggregateId The aggregate id
   * @param string $aggregateType The aggregate type
   * @param array<string, mixed> $payload The payload
   * @param DateTimeImmutable $occurredAt The occurred at
   */
  public function __construct(
    public Uuid $eventId,
    public string $aggregateId,
    public string $aggregateType,
    public array $payload,
    public DateTimeImmutable $occurredAt,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method fromDomain.
   *
   * Create an application event from a domain event
   *
   * @param DomainEvent $event The domain event
   *
   * @return self The application event
   */
  public static function fromDomain(DomainEvent $event): self
  {
    return new self(
      eventId: $event->eventId(),
      aggregateId: $event->aggregateId(),
      aggregateType: $event->aggregateType(),
      payload: $event->payload(),
      occurredAt: $event->occurredAt(),
    );
  }
  // #endregion
}
