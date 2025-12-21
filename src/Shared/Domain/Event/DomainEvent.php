<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;
use Shared\Domain\ValueObject\Uuid;

/**
 * Event DomainEvent.
 *
 * Event for domain events.
 *
 * @category Domain Event
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface DomainEvent
{
  // #region Methods
  /**
   * Method eventId.
   *
   * Get the event id.
   *
   * @since 1.0.0
   *
   * @return Uuid the event id
   */
  public function eventId(): Uuid;

  /**
   * Method occurredAt.
   *
   * Get the occurred at.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the occurred at
   */
  public function occurredAt(): DateTimeImmutable;

  /**
   * Method aggregateId.
   *
   * Returns the identifier of the aggregate
   * that produced the event.
   *
   * @return string the aggregate identifier
   */
  public function aggregateId(): string;

  /**
   * Method aggregateType.
   *
   * Returns the aggregate type emitting the event.
   *
   * @return string the aggregate type
   */
  public function aggregateType(): string;

  /**
   * Method payload.
   *
   * Returns the event payload as an array.
   *
   * @return array<string, mixed> the event payload
   */
  public function payload(): array;
  // #endregion
}
