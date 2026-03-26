<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationFacilityStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{FacilityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationFacilityStatistics\{GetOrganizationFacilityStatisticsHandler, GetOrganizationFacilityStatisticsQuery, GetOrganizationFacilityStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationFacilityStatisticsHandler::class)]
final class GetOrganizationFacilityStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  #[Test]
  public function testInvokeReturnsDetailedFacilityStatistics(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.read')
      ->willReturn(true);

    /** @var OrganizationRepositoryPort&MockObject $orgRepository */
    $orgRepository = $this->createMock(OrganizationRepositoryPort::class);
    $orgRepository->expects(self::once())
      ->method('findById')
      ->willReturn(Organization::create(
        id: OrganizationId::fromString(self::ORG_ID),
        name: new OrganizationName('Test Org'),
        ownerUserId: '550e8400-e29b-41d4-a716-446655440099',
      ));

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::once())->method('countFacilities')->with(self::ORG_ID)->willReturn(8);
    $facilityStatistics->expects(self::once())->method('countActiveFacilities')->with(self::ORG_ID)->willReturn(6);
    $facilityStatistics->expects(self::once())->method('countFacilitiesByType')->with(self::ORG_ID)->willReturn([
      'site' => 1,
      'building' => 2,
      'floor' => 3,
      'zone' => 1,
      'area' => 1,
    ]);

    $handler = new GetOrganizationFacilityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      facilityStatistics: $facilityStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationFacilityStatisticsQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationFacilityStatisticsResult::class, $result);
    self::assertSame(8, $result->totalCount);
    self::assertSame(6, $result->activeCount);
    self::assertSame(2, $result->archivedCount);
    self::assertSame(3, $result->countsByType['floor']);
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

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::never())->method('countFacilities');

    $handler = new GetOrganizationFacilityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      facilityStatistics: $facilityStatistics,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationFacilityStatisticsQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.facilities.read')
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

    /** @var FacilityStatisticsPort&MockObject $facilityStatistics */
    $facilityStatistics = $this->createMock(FacilityStatisticsPort::class);
    $facilityStatistics->expects(self::never())->method('countFacilities');

    $handler = new GetOrganizationFacilityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      facilityStatistics: $facilityStatistics,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationFacilityStatisticsQuery(self::ORG_ID, self::USER_ID));
  }
}
