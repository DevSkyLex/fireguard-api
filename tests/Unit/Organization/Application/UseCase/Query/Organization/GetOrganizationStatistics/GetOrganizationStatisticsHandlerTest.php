<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationInvitationRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationStatistics\{GetOrganizationStatisticsHandler, GetOrganizationStatisticsQuery, GetOrganizationStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationStatisticsHandler::class)]
final class GetOrganizationStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  // #region Methods
  #[Test]
  public function testInvokeReturnsStatisticsUsingFacilityStatisticsPort(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.read')
      ->willReturn(true);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (OrganizationId $id): bool => self::ORG_ID === (string) $id))
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $memberRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(5);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(3);

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::once())
      ->method('countPendingByOrganizationId')
      ->willReturn(2);

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())
      ->method('countFacilities')
      ->with(self::ORG_ID)
      ->willReturn(9);
    $facilityStatistics->expects(self::once())
      ->method('countActiveFacilities')
      ->with(self::ORG_ID)
      ->willReturn(7);

    $handler = new GetOrganizationStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
      facilityStatistics: $facilityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationStatisticsQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
    ));

    self::assertInstanceOf(GetOrganizationStatisticsResult::class, $result);
    self::assertSame(5, $result->memberCount);
    self::assertSame(3, $result->roleCount);
    self::assertSame(9, $result->facilityCount);
    self::assertSame(7, $result->activeFacilityCount);
    self::assertSame(2, $result->pendingInvitationCount);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::never())->method('hasPermission');

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);

    /** @var OrganizationMemberRepositoryPort&MockObject $memberRepository */
    $memberRepository = $this->createMock(OrganizationMemberRepositoryPort::class);

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::never())->method('countFacilities');
    $facilityStatistics->expects(self::never())->method('countActiveFacilities');

    $handler = new GetOrganizationStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
      facilityStatistics: $facilityStatistics,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationStatisticsQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.read')
      ->willReturn(false);

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
    $memberRepository->expects(self::never())->method('countByOrganizationId');

    /** @var OrganizationRoleRepositoryPort&MockObject $roleRepository */
    $roleRepository = $this->createMock(OrganizationRoleRepositoryPort::class);
    $roleRepository->expects(self::never())->method('countByOrganizationId');

    /** @var OrganizationInvitationRepositoryPort&MockObject $invitationRepository */
    $invitationRepository = $this->createMock(OrganizationInvitationRepositoryPort::class);
    $invitationRepository->expects(self::never())->method('countPendingByOrganizationId');

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::never())->method('countFacilities');

    $handler = new GetOrganizationStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      memberRepository: $memberRepository,
      roleRepository: $roleRepository,
      invitationRepository: $invitationRepository,
      facilityStatistics: $facilityStatistics,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationStatisticsQuery(
      organizationId: self::ORG_ID,
      userId: self::USER_ID,
    ));
  }
  // #endregion
}
