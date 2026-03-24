<?php

declare(strict_types=1);

namespace Tests\Unit\Onboarding\Infrastructure\EventSubscriber;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Onboarding\Domain\Event\OrganizationOnboardingSessionCompletedEvent;
use Onboarding\Infrastructure\EventSubscriber\OnboardingNotificationSubscriber;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use User\Application\Contract\User\UserView;
use User\Application\UseCase\Query\User\GetUser\GetUserResult;

use function in_array;

#[CoversClass(OnboardingNotificationSubscriber::class)]
final class OnboardingNotificationSubscriberTest extends TestCase
{
  #[Test]
  public function testGetSubscribedEventsRegistersCompletedEventHandler(): void
  {
    $events = OnboardingNotificationSubscriber::getSubscribedEvents();

    self::assertArrayHasKey(OrganizationOnboardingSessionCompletedEvent::class, $events);
    self::assertSame('onOrganizationOnboardingSessionCompleted', $events[OrganizationOnboardingSessionCompletedEvent::class]);
  }

  #[Test]
  public function testNotificationSentWithEmailAndMercureWhenUserEmailResolved(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return in_array(NotificationChannel::EMAIL, $request->channels, true)
          && in_array(NotificationChannel::MERCURE, $request->channels, true)
          && 'user@example.com' === $request->recipientEmail
          && 'user-001' === $request->recipientUserId
          && 'org-001' === $request->payload['organizationId'];
      }))
      ->willReturn($this->makeSentNotification());

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult($this->buildUserView('user@example.com')));

    $subscriber = new OnboardingNotificationSubscriber(
      notificationPort: $notificationPort,
      queryBus: $queryBus,
      logger: $this->createMock(LoggerInterface::class),
    );

    $subscriber->onOrganizationOnboardingSessionCompleted($this->buildEvent());
  }

  #[Test]
  public function testNotificationSentWithMercureOnlyWhenUserEmailIsNull(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return [NotificationChannel::MERCURE] === $request->channels
          && null === $request->recipientEmail;
      }))
      ->willReturn($this->makeSentNotification());

    // GetUserResult with user = null → email cannot be resolved
    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult(null));

    $subscriber = new OnboardingNotificationSubscriber(
      notificationPort: $notificationPort,
      queryBus: $queryBus,
      logger: $this->createMock(LoggerInterface::class),
    );

    $subscriber->onOrganizationOnboardingSessionCompleted($this->buildEvent());
  }

  #[Test]
  public function testNotificationSentWithMercureOnlyWhenUserQueryFails(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return [NotificationChannel::MERCURE] === $request->channels
          && null === $request->recipientEmail;
      }))
      ->willReturn($this->makeSentNotification());

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willThrowException(new RuntimeException('User not found'));

    /** @var LoggerInterface&MockObject $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())->method('warning');

    $subscriber = new OnboardingNotificationSubscriber(
      notificationPort: $notificationPort,
      queryBus: $queryBus,
      logger: $logger,
    );

    $subscriber->onOrganizationOnboardingSessionCompleted($this->buildEvent());
  }

  #[Test]
  public function testNotificationFailureIsCaughtAndLogged(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->method('send')
      ->willThrowException(new RuntimeException('Notification delivery failed'));

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult($this->buildUserView()));

    /** @var LoggerInterface&MockObject $logger */
    $logger = $this->createMock(LoggerInterface::class);
    $logger->expects(self::once())
      ->method('error')
      ->with('Failed to send onboarding completion notification.', self::anything());

    $subscriber = new OnboardingNotificationSubscriber(
      notificationPort: $notificationPort,
      queryBus: $queryBus,
      logger: $logger,
    );

    // Must not throw
    $subscriber->onOrganizationOnboardingSessionCompleted($this->buildEvent());
  }

  #[Test]
  public function testNotificationPayloadContainsSessionAndOrganizationId(): void
  {
    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request): bool {
        return 'session-001' === $request->payload['sessionId']
          && 'org-001' === $request->payload['organizationId']
          && isset($request->payload['completedAt']);
      }))
      ->willReturn($this->makeSentNotification());

    /** @var QueryBusPort&MockObject $queryBus */
    $queryBus = $this->createMock(QueryBusPort::class);
    $queryBus->method('ask')
      ->willReturn(new GetUserResult($this->buildUserView()));

    $subscriber = new OnboardingNotificationSubscriber(
      notificationPort: $notificationPort,
      queryBus: $queryBus,
      logger: $this->createMock(LoggerInterface::class),
    );

    $subscriber->onOrganizationOnboardingSessionCompleted($this->buildEvent());
  }

  private function makeSentNotification(): SentNotification
  {
    return new SentNotification(
      id: 'notif-001',
      type: 'onboarding_completed',
      subject: 'Onboarding complete',
      body: '',
      channels: [],
      payload: [],
      channelDelivery: [],
      createdAt: new DateTimeImmutable(),
    );
  }

  private function buildEvent(): OrganizationOnboardingSessionCompletedEvent
  {
    return new OrganizationOnboardingSessionCompletedEvent(
      sessionId: 'session-001',
      userId: 'user-001',
      targetOrganizationId: 'org-001',
      completedAt: new DateTimeImmutable('2026-03-15T10:00:00+00:00'),
    );
  }

  private function buildUserView(string $email = 'user@example.com'): UserView
  {
    return new UserView(
      id: 'user-001',
      username: 'testuser',
      email: $email,
      firstName: 'Test',
      lastName: 'User',
      avatarUrl: null,
      status: 'active',
      emailVerified: true,
      tenantId: null,
      createdAt: new DateTimeImmutable(),
      lastLoginAt: null,
      canLogin: true,
    );
  }
}
