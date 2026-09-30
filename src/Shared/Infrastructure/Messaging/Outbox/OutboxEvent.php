<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

/** Message OutboxEvent. The identity and event snapshot survive every transport retry. */
final readonly class OutboxEvent
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * Captures an event snapshot and stable identity for outbox delivery retries.
   *
   * @access public
   *
   * @param string $id stable outbox message identifier
   * @param object $event event payload dispatched through the outbox
   * @param ?string $actorUserId optional user attributed as the event actor
   *
   * @return void
   */
  public function __construct(public string $id, public object $event, public ?string $actorUserId = null)
  {
  }
  // #endregion
}
