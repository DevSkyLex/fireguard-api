<?php

declare(strict_types=1);

namespace Shared\Infrastructure\Messaging\Outbox;

use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/** Handler DeliverOutboxEventHandler. Failures propagate to Messenger's retry/dead-letter policy. */
#[AsMessageHandler]
final readonly class DeliverOutboxEventHandler
{
  public function __construct(private DurableEventContext $context, private SymfonyEventDispatcherAdapter $dispatcher)
  {
  }

  public function __invoke(OutboxEvent $message): void
  {
    $this->context->deliver($message->id, fn () => $this->dispatcher->dispatch($message->event), $message->actorUserId ?? null);
  }
}
