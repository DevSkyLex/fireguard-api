<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics;

use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{NonConformityStatisticsPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Organization\GetOrganizationNonConformityStatistics\{GetOrganizationNonConformityStatisticsHandler, GetOrganizationNonConformityStatisticsQuery, GetOrganizationNonConformityStatisticsResult};
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[CoversClass(GetOrganizationNonConformityStatisticsHandler::class)]
final class GetOrganizationNonConformityStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440777';

  #[Test]
  public function testInvokeReturnsDetailedNonConformityStatistics(): void
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

    /** @var NonConformityStatisticsPort&MockObject $statistics */
    $statistics = $this->createMock(NonConformityStatisticsPort::class);
    $statistics->expects(self::once())->method('countNonConformities')->with(self::ORG_ID)->willReturn(18);
    $statistics->expects(self::once())->method('countNonConformitiesByStatus')->with(self::ORG_ID)->willReturn([
      'open' => 5,
      'in_progress' => 4,
      'done' => 7,
      'waived' => 2,
    ]);
    $statistics->expects(self::once())->method('countNonConformitiesBySeverity')->with(self::ORG_ID)->willReturn([
      'low' => 3,
      'medium' => 6,
      'high' => 5,
      'critical' => 4,
    ]);

    $handler = new GetOrganizationNonConformityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      nonConformityStatistics: $statistics,
    );

    $result = $handler->__invoke(new GetOrganizationNonConformityStatisticsQuery(self::ORG_ID, self::USER_ID));

    self::assertInstanceOf(GetOrganizationNonConformityStatisticsResult::class, $result);
    self::assertSame(18, $result->totalCount);
    self::assertSame(5, $result->openCount);
    self::assertSame(4, $result->inProgressCount);
    self::assertSame(7, $result->doneCount);
    self::assertSame(2, $result->waivedCount);
    self::assertSame(3, $result->lowSeverityCount);
    self::assertSame(6, $result->mediumSeverityCount);
    self::assertSame(5, $result->highSeverityCount);
    self::assertSame(4, $result->criticalSeverityCount);
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

    /** @var NonConformityStatisticsPort&MockObject $statistics */
    $statistics = $this->createMock(NonConformityStatisticsPort::class);
    $statistics->expects(self::never())->method('countNonConformities');

    $handler = new GetOrganizationNonConformityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      nonConformityStatistics: $statistics,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new GetOrganizationNonConformityStatisticsQuery(self::ORG_ID, self::USER_ID));
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

    /** @var NonConformityStatisticsPort&MockObject $statistics */
    $statistics = $this->createMock(NonConformityStatisticsPort::class);
    $statistics->expects(self::never())->method('countNonConformities');

    $handler = new GetOrganizationNonConformityStatisticsHandler(
      authorization: $authorization,
      organizationRepository: $orgRepository,
      nonConformityStatistics: $statistics,
    );

    $this->expectException(OrganizationAccessDeniedException::class);

    $handler->__invoke(new GetOrganizationNonConformityStatisticsQuery(self::ORG_ID, self::USER_ID));
  }
}
