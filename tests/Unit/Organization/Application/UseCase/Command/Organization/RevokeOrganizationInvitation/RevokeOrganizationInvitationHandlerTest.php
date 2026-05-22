<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Contract\Notification\SentNotification;
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\OrganizationInvitationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\RevokeOrganizationInvitation\{RevokeOrganizationInvitationCommand, RevokeOrganizationInvitationHandler, RevokeOrganizationInvitationResult};
use Organization\Domain\Exception\OrganizationInvitationNotFoundException;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};
use Shared\Domain\ValueObject\Email;
use Tests\Support\Factory\UserTestFactory;
use User\Application\Port\Outbound\UserRepositoryPort;

use function is_string;
use function sprintf;

#[CoversClass(RevokeOrganizationInvitationHandler::class)]
final class RevokeOrganizationInvitationHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeReturnsResultWhenNotificationDispatchFails(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442200';
    $invitationId = '550e8400-e29b-41d4-a716-446655442201';
    $roleId = '550e8400-e29b-41d4-a716-446655442202';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655442203';
    $revokerUserId = '550e8400-e29b-41d4-a716-446655442204';
    $email = 'member@example.com';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'hashed-token',
      invitedByUserId: $inviterUserId,
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationInvitationId $id): bool => $invitationId === (string) $id))
      ->willReturn($invitation);
    $invitationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (OrganizationInvitation $updatedInvitation) use ($revokerUserId): bool {
        return 'revoked' === $updatedInvitation->status()->value
          && $revokerUserId === $updatedInvitation->revokedByUserId()
          && $updatedInvitation->revokedAt() instanceof DateTimeImmutable;
      }));
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->with(self::callback(static fn (Email $value): bool => $email === (string) $value))
      ->willReturn(UserTestFactory::createActive('550e8400-e29b-41d4-a716-446655442205', $email));

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($email, $organizationId, $invitationId): bool {
        return NotificationType::ORGANIZATION_INVITATION_REVOKED === $request->type
          && 'Invitation revoked' === $request->subject
          && 'Your organization invitation has been revoked.' === $request->body
          && [NotificationChannel::EMAIL, NotificationChannel::MERCURE] === $request->channels
          && '550e8400-e29b-41d4-a716-446655442205' === $request->recipientUserId
          && $email === $request->recipientEmail
          && $organizationId === ($request->payload['organizationId'] ?? null)
          && $invitationId === ($request->payload['invitationId'] ?? null)
          && is_string($request->payload['revokedAt'] ?? null);
      }))
      ->willThrowException(new RuntimeException('Mailer unavailable.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Invitation revoked notification dispatch failed.',
        [
          'organizationId' => $organizationId,
          'invitationId' => $invitationId,
          'recipientUserId' => '550e8400-e29b-41d4-a716-446655442205',
          'recipientEmail' => $email,
          'error' => 'Mailer unavailable.',
        ],
      );

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new RevokeOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new RevokeOrganizationInvitationCommand(
      organizationId: $organizationId,
      invitationId: $invitationId,
      revokedByUserId: $revokerUserId,
    ));

    self::assertInstanceOf(RevokeOrganizationInvitationResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($email, $result->email);
    self::assertSame('revoked', $result->status);
    self::assertSame($inviterUserId, $result->invitedByUserId);
    self::assertSame($revokerUserId, $result->revokedByUserId);
    self::assertSame([$roleId], $result->roleIds);
    self::assertInstanceOf(DateTimeImmutable::class, $result->revokedAt);
  }

  #[Test]
  public function testInvokeLogsWhenEmailChannelIsReportedAsUndelivered(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655442210';
    $invitationId = '550e8400-e29b-41d4-a716-446655442211';
    $email = 'member@example.com';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId($organizationId),
      email: new Email($email),
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655442213',
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findById')->willReturn($invitation);
    $invitationRepository->expects(self::once())->method('save')->with(self::isInstanceOf(OrganizationInvitation::class));
    $invitationRepository->expects(self::once())->method('findRoleIdsForInvitation')->willReturn([]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449220',
        type: NotificationType::ORGANIZATION_INVITATION_REVOKED,
        subject: 'Invitation revoked',
        body: 'Your organization invitation has been revoked.',
        channels: [NotificationChannel::EMAIL->value],
        payload: ['organizationId' => $organizationId],
        channelDelivery: [NotificationChannel::EMAIL->value => false],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientEmail: $email,
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Invitation revoked email delivery failed.',
        [
          'organizationId' => $organizationId,
          'invitationId' => $invitationId,
          'recipientUserId' => null,
          'recipientEmail' => $email,
        ],
      );

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new RevokeOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new RevokeOrganizationInvitationCommand(
      organizationId: $organizationId,
      invitationId: $invitationId,
      revokedByUserId: '550e8400-e29b-41d4-a716-446655442214',
    ));

    self::assertInstanceOf(RevokeOrganizationInvitationResult::class, $result);
    self::assertSame('revoked', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenInvitationBelongsToAnotherOrganization(): void
  {
    $invitationId = '550e8400-e29b-41d4-a716-446655442221';

    $invitation = OrganizationInvitation::reconstitute(
      id: new OrganizationInvitationId($invitationId),
      organizationId: new OrganizationId('550e8400-e29b-41d4-a716-446655442222'),
      email: new Email('member@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655442223',
      status: OrganizationInvitationStatus::PENDING,
      expiresAt: new DateTimeImmutable('+7 days'),
      createdAt: new DateTimeImmutable('-1 day'),
      updatedAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())->method('findById')->willReturn($invitation);
    $invitationRepository->expects(self::never())->method('save');
    $invitationRepository->expects(self::never())->method('findRoleIdsForInvitation');

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

    $handler = new RevokeOrganizationInvitationHandler(
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      transactionManager: $transactionManager,
    );

    $this->expectException(OrganizationInvitationNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Organization invitation with ID "%s" not found.', $invitationId));

    $handler->__invoke(new RevokeOrganizationInvitationCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442224',
      invitationId: $invitationId,
      revokedByUserId: '550e8400-e29b-41d4-a716-446655442225',
    ));
  }
}
