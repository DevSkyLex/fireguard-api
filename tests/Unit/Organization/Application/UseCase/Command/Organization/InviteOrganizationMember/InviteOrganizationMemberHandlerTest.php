<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\InviteOrganizationMember;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Command\Organization\InviteOrganizationMember\{InviteOrganizationMemberCommand, InviteOrganizationMemberHandler, InviteOrganizationMemberResult};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{MailerPort, TransactionManagerPort};
use User\Application\Port\Outbound\UserRepositoryPort;

#[CoversClass(InviteOrganizationMemberHandler::class)]
final class InviteOrganizationMemberHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeCreatesInvitationAndSendsEmail(): void
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
    $invitationRepository->expects(self::once())
      ->method('findPendingByOrganizationAndEmail')
      ->willReturn(null);
    $invitationRepository->expects(self::once())
      ->method('save');
    $invitationRepository->expects(self::once())
      ->method('replaceRoleIds');
    $invitationRepository->expects(self::once())
      ->method('findRoleIdsForInvitation')
      ->willReturn([$roleId]);

    /** @var UserRepositoryPort&MockObject $userRepository */
    $userRepository = $this->createMock(UserRepositoryPort::class);
    $userRepository->expects(self::once())
      ->method('findByEmail')
      ->willReturn(null);

    /** @var MailerPort&MockObject $mailer */
    $mailer = $this->createMock(MailerPort::class);
    $mailer->expects(self::once())
      ->method('send')
      ->with(
        [$email],
        self::stringContains('Invitation to join'),
        self::stringContains('Use this token'),
      );

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(OrganizationInvitationId::class)
      ->willReturn(new OrganizationInvitationId($invitationId));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $handler = new InviteOrganizationMemberHandler(
      organizationRepository: $organizationRepository,
      roleRepository: $roleRepository,
      memberRepository: $memberRepository,
      invitationRepository: $invitationRepository,
      userRepository: $userRepository,
      mailer: $mailer,
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
    self::assertSame('pending', $result->status);
    self::assertSame($inviterUserId, $result->invitedByUserId);
    self::assertSame([$roleId], $result->roleIds);
  }
}
