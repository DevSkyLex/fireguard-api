<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{InspectionStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationInspectionStatistics\{GetOrganizationInspectionStatisticsHandler, GetOrganizationInspectionStatisticsQuery, GetOrganizationInspectionStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationInspectionStatisticsHandler::class)]
final class GetOrganizationInspectionStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  #[Test]
  public function testInvokeReturnsDetailedInspectionStatistics(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
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

    /** @var InspectionStatisticsPort&MockObject $inspectionStatistics */
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::once())->method('countInspections')->with(self::ORG_ID)->willReturn(21);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByStatus')->with(self::ORG_ID)->willReturn([
      'draft' => 3,
      'submitted' => 7,
      'closed' => 11,
    ]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByResult')->with(self::ORG_ID)->willReturn([
      'pass' => 12,
      'fail' => 6,
      'partial' => 3,
    ]);
    $inspectionStatistics->expects(self::once())->method('countInspectionsByInspectorType')->with(self::ORG_ID)->willReturn([
      'user' => 16,
      'external' => 5,
    ]);
    $inspectionStatistics->expects(self::exactly(2))->method('countInspectionsPerformedSince')->with(self::equalTo(self::ORG_ID), self::isString())->willReturnOnConsecutiveCalls(4, 13);

    $handler = new GetOrganizationInspectionStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      inspectionStatistics: $inspectionStatistics,
    );

    $result = $handler->__invoke(new GetOrganizationInspectionStatisticsQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationInspectionStatisticsResult::class, $result);
    self::assertSame(21, $result->totalCount);
    self::assertSame(3, $result->draftCount);
    self::assertSame(7, $result->submittedCount);
    self::assertSame(11, $result->closedCount);
    self::assertSame(12, $result->passCount);
    self::assertSame(6, $result->failCount);
    self::assertSame(3, $result->partialCount);
    self::assertSame(16, $result->countsByInspectorType['user']);
    self::assertSame(5, $result->countsByInspectorType['external']);
    self::assertSame(4, $result->performedLast7DaysCount);
    self::assertSame(13, $result->performedLast30DaysCount);
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

    /** @var InspectionStatisticsPort&MockObject $inspectionStatistics */
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::never())->method('countInspections');

    $handler = new GetOrganizationInspectionStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      inspectionStatistics: $inspectionStatistics,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationInspectionStatisticsQuery(self::ORG_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with(self::USER_ID, self::ORG_ID, 'organization.inspection.read')
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

    /** @var InspectionStatisticsPort&MockObject $inspectionStatistics */
    $inspectionStatistics = $this->createMock(InspectionStatisticsPort::class);
    $inspectionStatistics->expects(self::never())->method('countInspections');

    $handler = new GetOrganizationInspectionStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      inspectionStatistics: $inspectionStatistics,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationInspectionStatisticsQuery(self::ORG_ID, self::USER_ID));
  }
}
