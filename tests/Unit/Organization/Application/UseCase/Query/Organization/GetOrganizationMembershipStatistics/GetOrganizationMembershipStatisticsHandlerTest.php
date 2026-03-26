<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationMembershipStatistics\{GetOrganizationMembershipStatisticsHandler, GetOrganizationMembershipStatisticsQuery, GetOrganizationMembershipStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationInvitation\OrganizationInvitation;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationInvitationId, OrganizationInvitationStatus, OrganizationMemberId, OrganizationName, OrganizationRoleId, OrganizationRoleName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\Email;

#[CoversClass(GetOrganizationMembershipStatisticsHandler::class)]
final class GetOrganizationMembershipStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  #[Test]
  public function testInvokeReturnsDetailedMembershipStatistics(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(3))
      ->method('hasPermission')
      ->withAnyParameters()
      ->willReturnMap([
        [self::USER_ID, self::ORG_ID, 'organization.members.read', true],
        [self::USER_ID, self::ORG_ID, 'organization.roles.read', true],
        [self::USER_ID, self::ORG_ID, 'organization.members.manage', true],
      ]);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([
        OrganizationMember::reconstitute(
          id: OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440101'),
          organizationId: OrganizationId::fromString(self::ORG_ID),
          userId: '550e8400-e29b-41d4-a716-446655440201',
          isActive: true,
          joinedAt: new DateTimeImmutable('2025-01-10T00:00:00+00:00'),
        ),
        OrganizationMember::reconstitute(
          id: OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440102'),
          organizationId: OrganizationId::fromString(self::ORG_ID),
          userId: '550e8400-e29b-41d4-a716-446655440202',
          isActive: true,
          joinedAt: new DateTimeImmutable('2025-01-11T00:00:00+00:00'),
        ),
        OrganizationMember::reconstitute(
          id: OrganizationMemberId::fromString('550e8400-e29b-41d4-a716-446655440103'),
          organizationId: OrganizationId::fromString(self::ORG_ID),
          userId: '550e8400-e29b-41d4-a716-446655440203',
          isActive: false,
          joinedAt: new DateTimeImmutable('2025-01-12T00:00:00+00:00'),
        ),
      ]);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([
        OrganizationRole::reconstitute(
          id: OrganizationRoleId::fromString('550e8400-e29b-41d4-a716-446655440301'),
          organizationId: OrganizationId::fromString(self::ORG_ID),
          name: new OrganizationRoleName('owner'),
          permissions: ['organization.*'],
          isSystem: true,
          createdAt: new DateTimeImmutable('2025-01-10T00:00:00+00:00'),
        ),
        OrganizationRole::reconstitute(
          id: OrganizationRoleId::fromString('550e8400-e29b-41d4-a716-446655440302'),
          organizationId: OrganizationId::fromString(self::ORG_ID),
          name: new OrganizationRoleName('inspector'),
          permissions: ['organization.members.read'],
          isSystem: false,
          createdAt: new DateTimeImmutable('2025-01-11T00:00:00+00:00'),
        ),
      ]);

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('findByOrganizationId')
      ->willReturn([
        $this->createInvitation(OrganizationInvitationStatus::PENDING, '550e8400-e29b-41d4-a716-446655440401'),
        $this->createInvitation(OrganizationInvitationStatus::ACCEPTED, '550e8400-e29b-41d4-a716-446655440402'),
        $this->createInvitation(OrganizationInvitationStatus::REVOKED, '550e8400-e29b-41d4-a716-446655440403'),
        $this->createInvitation(OrganizationInvitationStatus::EXPIRED, '550e8400-e29b-41d4-a716-446655440404'),
      ]);

    $handler = new GetOrganizationMembershipStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
    );

    $result = $handler->__invoke(new GetOrganizationMembershipStatisticsQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationMembershipStatisticsResult::class, $result);
    self::assertSame(3, $result->memberCount);
    self::assertSame(2, $result->activeMemberCount);
    self::assertSame(1, $result->inactiveMemberCount);
    self::assertSame(2, $result->roleCount);
    self::assertSame(1, $result->systemRoleCount);
    self::assertSame(1, $result->customRoleCount);
    self::assertSame(4, $result->invitationCount);
    self::assertSame(1, $result->pendingInvitationCount);
    self::assertSame(1, $result->acceptedInvitationCount);
    self::assertSame(1, $result->revokedInvitationCount);
    self::assertSame(1, $result->expiredInvitationCount);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())->method('findById')->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationId');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByOrganizationId');

    $handler = new GetOrganizationMembershipStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationMembershipStatisticsQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenOneRequiredPermissionIsMissing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::exactly(2))
      ->method('hasPermission')
      ->withAnyParameters()
      ->willReturnMap([
        [self::USER_ID, self::ORG_ID, 'organization.members.read', true],
        [self::USER_ID, self::ORG_ID, 'organization.roles.read', false],
      ]);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::never())->method('findByOrganizationId');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('findByOrganizationId');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('findByOrganizationId');

    $handler = new GetOrganizationMembershipStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationMembershipStatisticsQuery(self::ORG_ID, self::USER_ID));
  }

  private function createInvitation(OrganizationInvitationStatus $status, string $invitationId): OrganizationInvitation
  {
    return OrganizationInvitation::reconstitute(
      id: OrganizationInvitationId::fromString($invitationId),
      organizationId: OrganizationId::fromString(self::ORG_ID),
      email: new Email('person@example.com'),
      tokenHash: 'hashed-token',
      invitedByUserId: '550e8400-e29b-41d4-a716-446655440501',
      status: $status,
      expiresAt: new DateTimeImmutable('2025-02-01T00:00:00+00:00'),
      createdAt: new DateTimeImmutable('2025-01-10T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2025-01-10T00:00:00+00:00'),
      acceptedAt: OrganizationInvitationStatus::ACCEPTED === $status ? new DateTimeImmutable('2025-01-12T00:00:00+00:00') : null,
      acceptedByUserId: OrganizationInvitationStatus::ACCEPTED === $status ? '550e8400-e29b-41d4-a716-446655440601' : null,
      revokedAt: OrganizationInvitationStatus::REVOKED === $status ? new DateTimeImmutable('2025-01-13T00:00:00+00:00') : null,
      revokedByUserId: OrganizationInvitationStatus::REVOKED === $status ? '550e8400-e29b-41d4-a716-446655440602' : null,
    );
  }
}
