<?php

declare(strict_types=1);

namespace Shared\Domain\Event;

use DateTimeImmutable;

/**
 * Event DomainEvent
 *
 * Event for domain events.
 *
 * @category Domain Event
 * @package Shared\Domain\Event
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface DomainEvent
{
  //#region Methods
  /**
   * Method eventId
   * @method eventId(): string
   *
   * Get the event id.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The event id.
   */
  public function eventId(): string;

  /**
   * Method occurredAt
   * @method occurredAt(): DateTimeImmutable
   *
   * Get the occurred at.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The occurred at.
   */
  public function occurredAt(): DateTimeImmutable;
  //#endregion
}

