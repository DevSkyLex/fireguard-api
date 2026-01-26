<?php

declare(strict_types=1);

namespace Tests\Unit\User\Application\EventHandler;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\LoggerPort;
use Shared\Domain\ValueObject\Uuid;
use User\Application\EventHandler\UserEmailVerifiedEventHandler;
use User\Domain\Event\UserEmailVerifiedEvent;

/**
 * Test UserEmailVerifiedEventHandlerTest.
 *
 * @category EventHandler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserEmailVerifiedEventHandler::class)]
final class UserEmailVerifiedEventHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testInvokeLogsUserEmailVerified(): void
  {
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'User email verified',
        [
          'user_id' => 'user-1',
          'email' => 'jdoe@example.com',
        ],
      );

    $handler = new UserEmailVerifiedEventHandler($logger);

    $event = new UserEmailVerifiedEvent(
      eventId: new Uuid('00000000-0000-4000-a000-000000000002'),
      userId: 'user-1',
      email: 'jdoe@example.com',
      occurredAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
    );

    $handler($event);
  }
  // #endregion
}
