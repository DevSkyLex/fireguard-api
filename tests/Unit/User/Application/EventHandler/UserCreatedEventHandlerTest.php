<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\EventHandler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\ValueObject\Uuid;
use User\Application\EventHandler\UserCreatedEventHandler;
use User\Domain\Event\UserCreatedEvent;

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
          'username' => 'jdoe',
          'email' => 'jdoe@example.com',
        ],
      );

    $handler = new UserCreatedEventHandler($logger);

    $event = new UserCreatedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000001'),
      userId: 'user-1',
      username: 'jdoe',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    $handler($event);
  }
  // #endregion
}
