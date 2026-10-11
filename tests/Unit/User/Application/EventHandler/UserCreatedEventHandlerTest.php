<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\EventHandler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;
use User\Application\Contract\Event\UserCreatedEvent;
use User\Application\EventHandler\UserCreatedEventHandler;

/**
 * Test UserCreatedEventHandlerTest.
 *
 * @category EventHandler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserCreatedEventHandler::class)]
final class UserCreatedEventHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeLogsUserCreated(): void
  {
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'User created',
        [
          'user_id' => 'user-1',
        ],
      );

    $handler = new UserCreatedEventHandler($logger);

    $event = new UserCreatedEvent(
      eventId: '00000000-0000-4000-a000-000000000001',
      userId: 'user-1',
      username: 'jdoe',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    $handler($event);
  }
  // #endregion
}
