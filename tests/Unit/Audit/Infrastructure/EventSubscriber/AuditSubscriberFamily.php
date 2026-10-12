<?php

declare(strict_types=1);

namespace Tests\Unit\Audit\Infrastructure\EventSubscriber;

use LogicException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

use function method_exists;

/** Test facade retaining listener names while exercising owner-local semantic projections. */
final readonly class AuditSubscriberFamily
{
  /**
   * @param list<EventSubscriberInterface> $subscribers owner event projections
   */
  public function __construct(private array $subscribers)
  {
  }

  /**
   * Delivers one event to its owner projection through the preserved listener name.
   */
  public function deliver(string $method, object $event): void
  {
    foreach ($this->subscribers as $subscriber) {
      if (method_exists($subscriber, $method)) {
        $subscriber->{$method}($event);

        return;
      }
    }

    throw new LogicException('Unknown owner audit listener: ' . $method);
  }
}
