<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics;

use DateTimeImmutable;
use Intervention\Application\Contract\Statistics\{InterventionIdentifierCount, InterventionStatisticsAggregate};
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionSiteNamingPort, InterventionStatisticsGatewayPort};
use Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics\{GetInterventionStatisticsHandler, GetInterventionStatisticsQuery, GetInterventionStatisticsResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

#[CoversClass(GetInterventionStatisticsHandler::class)]
final class GetInterventionStatisticsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449101';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449102';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655449103';

  private const string RESPONSIBLE_ID = '550e8400-e29b-41d4-a716-446655449104';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var InterventionStatisticsGatewayPort&MockObject $statistics */
    $statistics = $this->createMock(InterventionStatisticsGatewayPort::class);
    $statistics->expects(self::never())->method('aggregate');

    $handler = new GetInterventionStatisticsHandler(
      statistics: $statistics,
      siteNaming: $this->createStub(InterventionSiteNamingPort::class),
      memberNaming: $this->createStub(InterventionMemberNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new GetInterventionStatisticsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheCallerIsOutsideTheOwningOrganization(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    /** @var InterventionStatisticsGatewayPort&MockObject $statistics */
    $statistics = $this->createMock(InterventionStatisticsGatewayPort::class);
    $statistics->expects(self::never())->method('aggregate');

    $handler = new GetInterventionStatisticsHandler(
      statistics: $statistics,
      siteNaming: $this->createStub(InterventionSiteNamingPort::class),
      memberNaming: $this->createStub(InterventionMemberNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new GetInterventionStatisticsQuery(self::USER_ID, self::ORG_ID));
  }

  #[Test]
  public function testInvokeZeroFillsEveryStatusAndPriorityKeyAndReturnsNullAverageWhenEmpty(): void
  {
    $now = new DateTimeImmutable('2026-08-13T10:00:00+00:00');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $aggregate = new InterventionStatisticsAggregate(
      total: 0,
      countsByStatus: [],
      countsByPriority: [],
      overdue: 0,
      dueSoon: 0,
      topSites: [],
      topResponsibles: [],
      averagePublicationDays: null,
    );

    /** @var InterventionStatisticsGatewayPort&MockObject $statistics */
    $statistics = $this->createMock(InterventionStatisticsGatewayPort::class);
    $statistics->expects(self::once())
      ->method('aggregate')
      ->with(self::ORG_ID, $now)
      ->willReturn($aggregate);

    /** @var InterventionSiteNamingPort&MockObject $siteNaming */
    $siteNaming = $this->createMock(InterventionSiteNamingPort::class);
    $siteNaming->expects(self::once())->method('findNamesByIds')->with(self::ORG_ID, [])->willReturn([]);

    /** @var InterventionMemberNamingPort&MockObject $memberNaming */
    $memberNaming = $this->createMock(InterventionMemberNamingPort::class);
    $memberNaming->expects(self::once())->method('displayNamesFor')->with(self::ORG_ID, [])->willReturn([]);

    $handler = new GetInterventionStatisticsHandler(
      statistics: $statistics,
      siteNaming: $siteNaming,
      memberNaming: $memberNaming,
      authorization: $authorization,
      clock: $clock,
    );

    $result = $handler->__invoke(new GetInterventionStatisticsQuery(self::USER_ID, self::ORG_ID));

    self::assertInstanceOf(GetInterventionStatisticsResult::class, $result);
    self::assertSame(0, $result->total);
    self::assertSame([
      'draft' => 0,
      'planned' => 0,
      'in_progress' => 0,
      'submitted' => 0,
      'changes_requested' => 0,
      'published' => 0,
      'abandoned' => 0,
    ], $result->byStatus);
    self::assertSame([
      'low' => 0,
      'normal' => 0,
      'high' => 0,
      'urgent' => 0,
    ], $result->byPriority);
    self::assertSame(0, $result->overdue);
    self::assertSame(0, $result->dueSoon);
    self::assertSame([], $result->bySite);
    self::assertSame([], $result->byResponsible);
    self::assertNull($result->averagePublicationDays);
  }

  #[Test]
  public function testInvokeResolvesSiteAndResponsibleNamesForTopEntries(): void
  {
    $now = new DateTimeImmutable('2026-08-13T10:00:00+00:00');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $aggregate = new InterventionStatisticsAggregate(
      total: 12,
      countsByStatus: ['draft' => 5, 'published' => 7],
      countsByPriority: ['normal' => 12],
      overdue: 2,
      dueSoon: 1,
      topSites: [new InterventionIdentifierCount(self::SITE_ID, 9)],
      topResponsibles: [new InterventionIdentifierCount(self::RESPONSIBLE_ID, 4)],
      averagePublicationDays: 3.5,
    );

    $statistics = $this->createStub(InterventionStatisticsGatewayPort::class);
    $statistics->method('aggregate')->willReturn($aggregate);

    /** @var InterventionSiteNamingPort&MockObject $siteNaming */
    $siteNaming = $this->createMock(InterventionSiteNamingPort::class);
    $siteNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with(self::ORG_ID, [self::SITE_ID])
      ->willReturn([self::SITE_ID => 'Main Warehouse']);

    /** @var InterventionMemberNamingPort&MockObject $memberNaming */
    $memberNaming = $this->createMock(InterventionMemberNamingPort::class);
    $memberNaming->expects(self::once())
      ->method('displayNamesFor')
      ->with(self::ORG_ID, [self::RESPONSIBLE_ID])
      ->willReturn([self::RESPONSIBLE_ID => 'Jane Doe']);

    $handler = new GetInterventionStatisticsHandler(
      statistics: $statistics,
      siteNaming: $siteNaming,
      memberNaming: $memberNaming,
      authorization: $authorization,
      clock: $clock,
    );

    $result = $handler->__invoke(new GetInterventionStatisticsQuery(self::USER_ID, self::ORG_ID));

    self::assertSame(12, $result->total);
    self::assertSame(5, $result->byStatus['draft']);
    self::assertSame(7, $result->byStatus['published']);
    self::assertSame(0, $result->byStatus['in_progress']);
    self::assertSame(12, $result->byPriority['normal']);
    self::assertSame(0, $result->byPriority['urgent']);
    self::assertSame(2, $result->overdue);
    self::assertSame(1, $result->dueSoon);
    self::assertCount(1, $result->bySite);
    self::assertSame(self::SITE_ID, $result->bySite[0]->siteId);
    self::assertSame('Main Warehouse', $result->bySite[0]->siteName);
    self::assertSame(9, $result->bySite[0]->count);
    self::assertCount(1, $result->byResponsible);
    self::assertSame(self::RESPONSIBLE_ID, $result->byResponsible[0]->memberId);
    self::assertSame('Jane Doe', $result->byResponsible[0]->displayName);
    self::assertSame(4, $result->byResponsible[0]->count);
    self::assertSame(3.5, $result->averagePublicationDays);
  }
}
