<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Shared\Application\Port\Outbound\DurableEventContextPort;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Class DurableEventContext
 *
 * Scopes event identity and actor attribution to one delivery, including nested dispatches.
 *
 * @category EventContext
 */
final class DurableEventContext implements DurableEventContextPort, ResetInterface
{
  // #region Properties
  /**
   * Property id
   *
   * Current durable event identifier; null outside delivery or after a reset.
   *
   * @access private
   */
  private ?string $id = null;

  /**
   * Property actor
   *
   * Optional originating user identifier, restored when a nested delivery ends.
   *
   * @access private
   */
  private ?string $actor = null;
  // #endregion

  // #region Methods
  /**
   * Method actorUserId
   *
   * Reads the actor attached to the currently active event delivery.
   *
   * @access public
   *
   * @return ?string the originating user identifier, or null when unavailable
   */
  public function actorUserId(): ?string
  {
    return $this->actor;
  }

  /**
   * Method eventId
   *
   * Reads the durable identifier used to correlate and deduplicate the active delivery.
   *
   * @access public
   *
   * @return ?string the current event identifier, or null outside delivery
   */
  public function eventId(): ?string
  {
    return $this->id;
  }

  /**
   * Method deliver
   *
   * Runs a delivery with its event identity and restores the previous context even on failure.
   *
   * @access public
   *
   * @param string $id durable event identity exposed while the callback runs
   * @param callable():void $delivery callback whose failures propagate after context restoration
   * @param ?string $actorUserId optional originating user identifier
   *
   * @return void
   */
  public function deliver(string $id, callable $delivery, ?string $actorUserId = null): void
  {
    $previous = $this->id;
    $previousActor = $this->actor;
    $this->id = $id;
    $this->actor = $actorUserId;

    try {
      $delivery();
    } finally {
      $this->id = $previous;
      $this->actor = $previousActor;
    }
  }

  /**
   * Method reset
   *
   * Clears both identifiers when Symfony resets services between work units.
   *
   * @access public
   *
   * @return void
   */
  public function reset(): void
  {
    $this->id = null;
    $this->actor = null;
  }
  // #endregion
}
