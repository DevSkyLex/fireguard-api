<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberHandler, InviteOrganizationMemberResult};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{LoggerPort, TransactionManagerPort};
use User\Application\Port\Outbound\UserRepositoryPort;

use function array_key_exists;
use function is_array;
use function is_string;
use function str_contains;

#[CoversClass(InviteOrganizationMemberHandler::class)]
final class InviteOrganizationMemberHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesInvitationAndSendsNotification(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441900';
    $invitationId = '550e8400-e29b-41d4-a716-446655441901';
    $roleId = '550e8400-e29b-41d4-a716-446655441902';
    $inviterUserId = '550e8400-e29b-41d4-a716-446655441903';
    $email = 'member@example.com';

    $organization = Organization::reconstitute(
      id: new OrganizationId($organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: $inviterUserId,
      isActive: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    $defaultRole = OrganizationRole::reconstitute(
      id: new OrganizationRoleId($roleId),
      organizationId: new OrganizationId($organizationId),
      name: new OrganizationRoleName('member'),
      permissions: ['organization.read'],
      isSystem: true,
      createdAt: new DateTimeImmutable('-1 day'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationAndName')
      ->willReturn($defaultRole);
    $roleRepository->expects(self::once())
      ->method('findByIdsInOrganization')
      ->willReturn([$defaultRole]);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())
      ->method('findByOrganizationAndUser');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $persistedInvitation = null;
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::exactly(2))
      ->method('save')
      ->willReturnCallback(static function (OrganizationInvitation $invitation) use (&$persistedInvitation): void {
        if (!$persistedInvitation instanceof OrganizationInvitation) {
          $persistedInvitation = $invitation;
        }
      });
    $invitationRepository->expects(self::once())
      ->method('findById')
      ->willReturnCallback(static function () use (&$persistedInvitation): ?OrganizationInvitation {
        return $persistedInvitation;
      });
    $invitationRepository->expects(self::once())
      ->method('replaceRoleIds');
    $invitationRepository->expects(self::exactly(2))
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($email): bool {
        $emailPayload = $request->deliveryPayload['email'] ?? null;
        $emailBody = is_array($emailPayload) ? ($emailPayload['body'] ?? null) : null;

        return 'organization.invitation' === $request->type
          && [NotificationChannel::EMAIL] === $request->channels
          && null === $request->recipientUserId
          && $email === $request->recipientEmail
          && !array_key_exists('token', $request->payload)
          && !str_contains($request->body, 'Use this token')
          && str_contains($request->subject, 'Invitation to join')
          && is_string($emailBody)
          && str_contains($emailBody, 'Use this token');
      }))
      ->willThrowException(new RuntimeException('Notification delivery failed.'));

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::exactly(2))
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::exactly(2))
      ->method('warning');

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      notificationPort: $notificationPort,
      logger: $logger,
      uuidFactory: $uuidFactory,
      transactionManager: $transactionManager,
    );

    $result = $handler->__invoke(new InviteOrganizationMemberCommand(
      organizationId: $organizationId,
      email: $email,
      invitedByUserId: $inviterUserId,
      roleIds: [],
    ));

    self::assertInstanceOf(InviteOrganizationMemberResult::class, $result);
    self::assertSame($invitationId, $result->invitationId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertSame($email, $result->email);
    self::assertSame('revoked', $result->status);
    self::assertSame($inviterUserId, $result->invitedByUserId);
    self::assertSame([$roleId], $result->roleIds);
  }
}
