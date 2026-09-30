<?php

declare(strict_types=1);

namespace Shared\Application\Port\Outbound;

/** Port DurableEventContextPort. Identifies an at-least-once event delivery. */
interface DurableEventContextPort
{
  /**
   * Method eventId
   *
   * Returns the durable event identifier for the current delivery context.
   *
   * @access public
   *
   * @return string|null the event identifier when available
   */
  public function eventId(): ?string;

  /**
   * Method actorUserId
   *
   * Returns the user identifier associated with the current event context.
   *
   * @access public
   *
   * @return string|null the actor identifier when available
   */
  public function actorUserId(): ?string;
}
