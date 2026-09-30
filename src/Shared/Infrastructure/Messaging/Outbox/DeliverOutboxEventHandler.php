<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Handler DeliverOutboxEventHandler. Failures propagate to Messenger's retry/dead-letter policy. */
#[AsMessageHandler]
final readonly class DeliverOutboxEventHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Wraps subscriber delivery in the persisted event and actor context.
   *
   * @access public
   *
   * @param DurableEventContext $context establishes the durable delivery context
   * @param SymfonyEventDispatcherAdapter $dispatcher dispatches the event to subscribers
   *
   * @return void
   */
  public function __construct(private DurableEventContext $context, private SymfonyEventDispatcherAdapter $dispatcher)
  {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke
   *
   * Delivers the outbox event inside its durable event and actor context.
   *
   * @access public
   *
   * @param OutboxEvent $message the persisted event envelope
   *
   * @return void no return value
   */
  public function __invoke(OutboxEvent $message): void
  {
    $this->context->deliver($message->id, fn () => $this->dispatcher->dispatch($message->event), $message->actorUserId ?? null);
  }
  // #endregion
}
