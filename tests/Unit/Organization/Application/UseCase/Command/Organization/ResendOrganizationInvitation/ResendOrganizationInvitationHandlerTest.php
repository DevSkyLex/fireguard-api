<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\Service\{OrganizationInvitationNotifier, OrganizationInvitationTokenHasher};
use Organization\Application\UseCase\Command\Organization\ResendOrganizationInvitation\{ResendOrganizationInvitationCommand, ResendOrganizationInvitationHandler, ResendOrganizationInvitationResult};
use Organization\Domain\Exception\{OrganizationInvitationNotFoundException, OrganizationInvitationNotificationFailedException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\EmailTranslatorTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function is_array;
use function is_string;
use function str_contains;

#[CoversClass(ResendOrganizationInvitationHandler::class)]
final class ResendOrganizationInvitationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeRenewsInvitationAndReturnsFreshAcceptUrl(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655443300';
    $invitationId = '550e8400-e29b-41d4-a716-446655443301';
    $roleId = '550e8400-e29b-41d4-a716-446655443302';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655443303';
    $email = 'member@example.com';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'old-hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::EXPIRED,
      expiresAt: new DateTimeImmutable('-1 day'),
      createdAt: new DateTimeImmutable('-10 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findById')->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (OrganizationInvitation $updated): bool => 'pending' === $updated->status()->value
        && 'old-hashed-token' !== $updated->tokenHash()));
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([$roleId]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findByEmail')->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($email): bool {
        $emailPayload = $request->deliveryPayload['email'] ?? null;
        $emailContext = is_array($emailPayload) ? ($emailPayload['context'] ?? null) : null;
        $emailAcceptUrl = is_array($emailContext) ? ($emailContext['acceptUrl'] ?? null) : null;

        return 'organization.invitation' === $request->type
          && [NotificationChannel::EMAIL] === $request->channels
          && $email === $request->recipientEmail
          && is_string($emailAcceptUrl)
          && str_contains($emailAcceptUrl, '/organizations/invitations/accept?token=');
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655443350',
        type: NotificationType::ORGANIZATION_INVITATION,
        subject: 'Invitation to join Fireguard HQ',
        body: 'body',
        channels: [NotificationChannel::EMAIL->value],
        payload: [],
        channelDelivery: [NotificationChannel::EMAIL->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientEmail: $email,
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new ResendOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new ResendOrganizationInvitationCommand(
      organizationId: $organizationId,
      invitationId: $invitationId,
      resentByUserId: $inviterUserId,
    ));

    self::assertInstanceOf(ResendOrganizationInvitationResult::class, $result);
    self::assertSame('pending', $result->status);
    self::assertSame([$roleId], $result->roleIds);
    self::assertStringContainsString('/organizations/invitations/accept?token=', $result->acceptUrl);
    self::assertGreaterThan(new DateTimeImmutable(), $result->expiresAt);
  }

  #[Test]
  public function testInvokeThrowsWhenInvitationBelongsToAnotherOrganization(): void
  {
    $invitationId = '550e8400-e29b-41d4-a716-446655443311';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655443312'),
      email: new Email('member@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655443313',
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findById')->willReturn($invitation);
    $invitationRepository->expects(self::never())->method('save');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::never())->method('findByEmail');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new ResendOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);

    $handler->__invoke(new ResendOrganizationInvitationCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443399',
      invitationId: $invitationId,
      resentByUserId: '550e8400-e29b-41d4-a716-446655443314',
    ));
  }

  #[Test]
  public function testInvokeRevokesInvitationWhenNotificationEmailFailsToDeliver(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655443400';
    $invitationId = '550e8400-e29b-41d4-a716-446655443401';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655443402';
    $email = 'member@example.com';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'old-hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::exactly(2))->method('findById')->willReturn($invitation);
    $invitationRepository->expects(self::exactly(2))
      ->method('save')
      ->with(self::isInstanceOf(OrganizationInvitation::class));
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([]);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())->method('findById')->willReturn($organization);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())->method('findByEmail')->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655443450',
        type: NotificationType::ORGANIZATION_INVITATION,
        subject: 'Invitation to join Fireguard HQ',
        body: 'body',
        channels: [NotificationChannel::EMAIL->value],
        payload: [],
        channelDelivery: [NotificationChannel::EMAIL->value => false],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientEmail: $email,
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('warning');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::exactly(2))
      ->method('transactional')
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $invitationNotifier = new OrganizationInvitationNotifier(
      $notificationPort,
      'http://localhost:4200',
      new OrganizationInvitationTokenHasher(),
      EmailTranslatorTestFactory::create(),
    );

    $handler = new ResendOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      organizationRepository: $organizationRepository,
      userRepository: $userRepository,
      invitationNotifier: $invitationNotifier,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationInvitationNotificationFailedException::class);

    $handler->__invoke(new ResendOrganizationInvitationCommand(
      organizationId: $organizationId,
      invitationId: $invitationId,
      resentByUserId: $inviterUserId,
    ));
  }
}
