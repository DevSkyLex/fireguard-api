<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\EventSubscriber;

use Auth\Domain\Event\Session\{LoginFailedEvent, UserLoggedInEvent};
use Auth\Infrastructure\EventSubscriber\EventLogSubscriber;
use Auth\Infrastructure\Logging\SecurityLogSanitizer;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

use function hash;

/**
 * Test EventLogSubscriberTest.
 *
 * @category Event Subscriber Tests
 */
#[CoversClass(className: EventLogSubscriber::class)]
final class EventLogSubscriberTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testGetSubscribedEvents(): void
  {
    self::assertSame([
      'auth.user_logged_in_event' => 'onUserLoggedIn',
      'auth.login_failed_event' => 'onLoginFailed',
    ], EventLogSubscriber::getSubscribedEvents());
  }

  #[Test]
  public function testOnUserLoggedInLogsInfo(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('info')
      ->with(
        'User authenticated successfully',
        self::callback(
          fn (array $context): bool => 'user-123' === $context['user_id']
          && 'user@example.com' === $context['email']
          && hash('sha256', 'user@example.com') === $context['email_hash']
          && '127.0.0.1' === $context['ip']
          && hash('sha256', '127.0.0.1') === $context['ip_hash'],
        ),
      );

    $subscriber = new EventLogSubscriber(
      $logger,
      new SecurityLogSanitizer(includePii: true),
    );

    $subscriber->onUserLoggedIn(new UserLoggedInEvent(
      userId: 'user-123',
      email: 'user@example.com',
      ipAddress: '127.0.0.1',
    ));
  }

  #[Test]
  public function testOnLoginFailedLogsWarning(): void
  {
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Login attempt failed',
        self::callback(
          fn (array $context): bool => 'user@example.com' === $context['email']
          && hash('sha256', 'user@example.com') === $context['email_hash']
          && '127.0.0.1' === $context['ip']
          && hash('sha256', '127.0.0.1') === $context['ip_hash']
          && 'invalid_password' === $context['reason'],
        ),
      );

    $subscriber = new EventLogSubscriber(
      $logger,
      new SecurityLogSanitizer(includePii: true),
    );

    $subscriber->onLoginFailed(new LoginFailedEvent(
      email: 'user@example.com',
      ipAddress: '127.0.0.1',
      reason: 'invalid_password',
    ));
  }
  // #endregion
}
