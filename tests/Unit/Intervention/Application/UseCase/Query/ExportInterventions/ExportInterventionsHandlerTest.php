<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Query\ExportInterventions;

use DateTimeImmutable;
use Intervention\Application\Contract\Export\InterventionExportCandidate;
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionSiteNamingPort, InterventionWorkflowGatewayPort};
use Intervention\Application\UseCase\Query\ExportInterventions\{ExportInterventionsHandler, ExportInterventionsQuery, ExportInterventionsResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionExportTooLargeException, InterventionNotFoundException};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

/**
 * Test ExportInterventionsHandlerTest.
 *
 * @category Handler Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExportInterventionsHandler::class)]
final class ExportInterventionsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655449201';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655449202';

  private const string SITE_ID = '550e8400-e29b-41d4-a716-446655449203';

  private const string RESPONSIBLE_ID = '550e8400-e29b-41d4-a716-446655449204';

  #[Test]
  public function testInvokeThrowsAccessDeniedWithoutPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORG_ID, 'organization.interventions.read')
      ->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    /** @var InterventionWorkflowGatewayPort&MockObject $gateway */
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::never())->method('countInterventions');
    $gateway->expects(self::never())->method('listInterventionExportCandidates');

    $handler = new ExportInterventionsHandler(
      gateway: $gateway,
      siteNaming: $this->createStub(InterventionSiteNamingPort::class),
      memberNaming: $this->createStub(InterventionMemberNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new ExportInterventionsQuery(self::USER_ID, self::ORG_ID, []));
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

    /** @var InterventionWorkflowGatewayPort&MockObject $gateway */
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::never())->method('countInterventions');

    $handler = new ExportInterventionsHandler(
      gateway: $gateway,
      siteNaming: $this->createStub(InterventionSiteNamingPort::class),
      memberNaming: $this->createStub(InterventionMemberNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new ExportInterventionsQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeThrowsWhenMatchCountExceedsTheCap(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    /** @var InterventionWorkflowGatewayPort&MockObject $gateway */
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())
      ->method('countInterventions')
      ->with(self::ORG_ID, [])
      ->willReturn(ExportInterventionsHandler::MAX_EXPORT_ROWS + 1);
    $gateway->expects(self::never())->method('listInterventionExportCandidates');

    $handler = new ExportInterventionsHandler(
      gateway: $gateway,
      siteNaming: $this->createStub(InterventionSiteNamingPort::class),
      memberNaming: $this->createStub(InterventionMemberNamingPort::class),
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $this->expectException(InterventionExportTooLargeException::class);

    $handler->__invoke(new ExportInterventionsQuery(self::USER_ID, self::ORG_ID, []));
  }

  #[Test]
  public function testInvokeResolvesSiteAndResponsibleNamesInBulkAndFallsBackToIdWhenUnresolved(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $resolved = new InterventionExportCandidate(
      id: 'intervention-1',
      name: 'Fire panel check',
      type: 'inspection_campaign',
      status: 'planned',
      priority: 'high',
      siteId: self::SITE_ID,
      responsibleId: self::RESPONSIBLE_ID,
      dueAt: '2026-09-01T00:00:00+00:00',
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );
    $unresolved = new InterventionExportCandidate(
      id: 'intervention-2',
      name: 'Unresolvable refs',
      type: 'site_setup',
      status: 'draft',
      priority: 'normal',
      siteId: 'unknown-site',
      responsibleId: 'unknown-member',
      dueAt: null,
      createdAt: '2026-08-01T00:00:00+00:00',
      updatedAt: '2026-08-02T00:00:00+00:00',
    );

    /** @var InterventionWorkflowGatewayPort&MockObject $gateway */
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())
      ->method('countInterventions')
      ->with(self::ORG_ID, [])
      ->willReturn(2);
    $gateway->expects(self::once())
      ->method('listInterventionExportCandidates')
      ->with(self::ORG_ID, [])
      ->willReturn([$resolved, $unresolved]);

    /** @var InterventionSiteNamingPort&MockObject $siteNaming */
    $siteNaming = $this->createMock(InterventionSiteNamingPort::class);
    $siteNaming->expects(self::once())
      ->method('findNamesByIds')
      ->with(self::ORG_ID, [self::SITE_ID, 'unknown-site'])
      ->willReturn([self::SITE_ID => 'Main Warehouse']);

    /** @var InterventionMemberNamingPort&MockObject $memberNaming */
    $memberNaming = $this->createMock(InterventionMemberNamingPort::class);
    $memberNaming->expects(self::once())
      ->method('displayNamesFor')
      ->with(self::ORG_ID, [self::RESPONSIBLE_ID, 'unknown-member'])
      ->willReturn([self::RESPONSIBLE_ID => 'Jane Doe']);

    $handler = new ExportInterventionsHandler(
      gateway: $gateway,
      siteNaming: $siteNaming,
      memberNaming: $memberNaming,
      authorization: $authorization,
      clock: $this->createStub(ClockPort::class),
    );

    $result = $handler->__invoke(new ExportInterventionsQuery(self::USER_ID, self::ORG_ID, []));

    self::assertInstanceOf(ExportInterventionsResult::class, $result);
    self::assertSame(2, $result->total);
    self::assertCount(2, $result->rows);
    self::assertSame(self::SITE_ID, $result->rows[0]->siteId);
    self::assertSame('Main Warehouse', $result->rows[0]->siteName);
    self::assertSame(self::RESPONSIBLE_ID, $result->rows[0]->responsibleId);
    self::assertSame('Jane Doe', $result->rows[0]->responsibleName);
    self::assertSame('unknown-site', $result->rows[1]->siteId);
    self::assertNull($result->rows[1]->siteName, 'An unresolvable site id must resolve to a null name, not an empty string.');
    self::assertSame('unknown-member', $result->rows[1]->responsibleId);
    self::assertNull($result->rows[1]->responsibleName);
  }

  #[Test]
  public function testInvokeResolvesTheDueOverdueFilterIntoGatewayKeysUsingTheClock(): void
  {
    $now = new DateTimeImmutable('2026-08-20T10:00:00+00:00');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    /** @var InterventionWorkflowGatewayPort&MockObject $gateway */
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())
      ->method('countInterventions')
      ->with(self::ORG_ID, self::callback(static function (array $filters) use ($now): bool {
        return !isset($filters['due'])
          && $filters['overdueAsOf'] === $now
          && [] !== $filters['overdueExcludedStatuses'];
      }))
      ->willReturn(0);
    $gateway->expects(self::once())
      ->method('listInterventionExportCandidates')
      ->willReturn([]);

    $siteNaming = $this->createStub(InterventionSiteNamingPort::class);
    $siteNaming->method('findNamesByIds')->willReturn([]);

    $memberNaming = $this->createStub(InterventionMemberNamingPort::class);
    $memberNaming->method('displayNamesFor')->willReturn([]);

    $handler = new ExportInterventionsHandler(
      gateway: $gateway,
      siteNaming: $siteNaming,
      memberNaming: $memberNaming,
      authorization: $authorization,
      clock: $clock,
    );

    $result = $handler->__invoke(new ExportInterventionsQuery(self::USER_ID, self::ORG_ID, ['due' => 'overdue']));

    self::assertSame(0, $result->total);
    self::assertSame([], $result->rows);
  }
}
