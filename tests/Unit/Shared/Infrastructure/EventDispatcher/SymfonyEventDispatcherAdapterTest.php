<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Infrastructure\EventDispatcher;

use Auth\Domain\Event\Session\UserLoggedInEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Shared\Infrastructure\EventDispatcher\SymfonyEventDispatcherAdapter;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * Test SymfonyEventDispatcherAdapterTest.
 *
 * @category Event Dispatcher Tests
 */
#[CoversClass(className: SymfonyEventDispatcherAdapter::class)]
final class SymfonyEventDispatcherAdapterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testDispatchUsesEventName(): void
  {
    $event = new UserLoggedInEvent('user-123', 'user@example.com', '127.0.0.1');

    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::once())
      ->method('dispatch')
      ->with($event, 'auth.user_logged_in_event')
      ->willReturn($event);

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('debug')
      ->with('Dispatching domain event', ['event' => 'auth.user_logged_in_event']);

    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $dispatcher,
      logger: $logger,
    );

    $adapter->dispatch($event);
  }

  #[Test]
  public function testDispatchAllDispatchesEachEvent(): void
  {
    $eventOne = new UserLoggedInEvent('user-1', 'one@example.com', null);
    $eventTwo = new UserLoggedInEvent('user-2', 'two@example.com', null);

    $dispatched = [];
    $dispatcher = $this->createMock(EventDispatcherInterface::class);
    $dispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(function ($event, string $name) use (&$dispatched) {
        $dispatched[] = [$event, $name];

        return $event;
      });

    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::exactly(2))
      ->method('debug')
      ->with('Dispatching domain event', ['event' => 'auth.user_logged_in_event']);

    $adapter = new SymfonyEventDispatcherAdapter(
      eventDispatcher: $dispatcher,
      logger: $logger,
    );

    $adapter->dispatchAll([$eventOne, $eventTwo]);

    self::assertSame([
      [$eventOne, 'auth.user_logged_in_event'],
      [$eventTwo, 'auth.user_logged_in_event'],
    ], $dispatched);
  }
  // #endregion
}
