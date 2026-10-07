<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Provider\Park;

use ApiPlatform\Metadata\{Get, GetCollection};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\UseCase\Query\NonConformity\ListOrganizationNonConformities\OrganizationNonConformityResult;
use Inspection\Application\UseCase\Query\Park\GetParkAnomaliesSummary\{GetParkAnomaliesSummaryQuery, GetParkAnomaliesSummaryResult};
use Inspection\Application\UseCase\Query\Park\ListParkAnomalies\ListParkAnomaliesQuery;
use Inspection\Presentation\Api\Operation\ParkAnomaliesOperations;
use Inspection\Presentation\Api\Provider\Park\ParkAnomaliesProvider;
use Inspection\Presentation\Api\Service\ParkAnomaliesAccess;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\{PaginatedResult, Pagination};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, NotFoundHttpException};

use function iterator_to_array;

final class ParkAnomaliesProviderTest extends TestCase
{
  #[Test]
  public function summaryMapsTheExactScopeAndCompleteBuckets(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(new GetParkAnomaliesSummaryQuery('org', 'fire', 'customer', 'facility'))->willReturn(new GetParkAnomaliesSummaryResult(8, ['low' => 2, 'medium' => 3, 'high' => 2, 'critical' => 1]));
    $result = $this->provider($queries)->provide(new Get(name: ParkAnomaliesOperations::SUMMARY), ['organizationId' => 'org']);
    self::assertInstanceOf(\Inspection\Presentation\Api\Dto\Output\Park\ParkAnomaliesSummaryOutput::class, $result);
    self::assertSame(8, $result->openAnomalies);
    self::assertSame(1, $result->bySeverity['critical']);
  }

  #[Test]
  public function listMapsCorrectInspectionAndEquipmentAndOnePagination(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $now = new DateTimeImmutable('2026-10-06T12:00:00+00:00');
    $queries->expects(self::once())->method('ask')->with(new ListParkAnomaliesQuery('org', 'fire', 'customer', 'facility', new Pagination(2, 2)))->willReturn(new PaginatedResult([
      new OrganizationNonConformityResult('finding', 'inspection', 'Valve issue', 'high', 'in_progress', null, null, null, $now, $now, 'equipment', 'SN-001'),
    ], 8, 2, 2));
    $result = $this->provider($queries)->provide(new GetCollection(name: ParkAnomaliesOperations::LIST), ['organizationId' => 'org']);
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $result);
    self::assertSame(8.0, $result->getTotalItems());
    $rows = iterator_to_array($result);
    self::assertInstanceOf(\Inspection\Presentation\Api\Dto\Output\NonConformity\NonConformityOutput::class, $rows[0]);
    self::assertSame('inspection', $rows[0]->inspectionId);
    self::assertSame('equipment', $rows[0]->equipmentId);
    self::assertSame('SN-001', $rows[0]->equipmentSerialNumber);
  }

  #[Test]
  public function directScopeIsForwardedToCountsAndRows(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::exactly(2))->method('ask')->willReturnCallback(static function (GetParkAnomaliesSummaryQuery|ListParkAnomaliesQuery $query): GetParkAnomaliesSummaryResult|PaginatedResult {
      self::assertFalse($query->includeDescendants);
      self::assertSame('fire', $query->family);
      self::assertSame('customer', $query->customerId);
      self::assertSame('facility', $query->facilityId);

      return $query instanceof GetParkAnomaliesSummaryQuery ? new GetParkAnomaliesSummaryResult(0, ['low' => 0, 'medium' => 0, 'high' => 0, 'critical' => 0]) : new PaginatedResult([], 0, 2, 2);
    });
    $provider = $this->provider($queries, querySuffix: '&includeDescendants=false');
    $provider->provide(new Get(name: ParkAnomaliesOperations::SUMMARY), ['organizationId' => 'org']);
    $provider->provide(new GetCollection(name: ParkAnomaliesOperations::LIST), ['organizationId' => 'org']);
  }

  #[Test]
  public function missingInspectionPermissionDoesNotQueryOrReturnZero(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::MISSING_PERMISSION)->provide(new Get(name: ParkAnomaliesOperations::SUMMARY), ['organizationId' => 'org']);
  }

  #[Test]
  public function missingEquipmentPermissionDoesNotQueryOrReturnZero(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::GRANTED, OrganizationAccessDecision::MISSING_PERMISSION)->provide(new Get(name: ParkAnomaliesOperations::SUMMARY), ['organizationId' => 'org']);
  }

  #[Test]
  public function foreignOrganizationIsHiddenBeforeQuerying(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(NotFoundHttpException::class);
    $this->provider($queries, OrganizationAccessDecision::OUTSIDE_SCOPE)->provide(new Get(name: ParkAnomaliesOperations::SUMMARY), ['organizationId' => 'org']);
  }

  private function provider(QueryBusPort $queries, OrganizationAccessDecision $inspection = OrganizationAccessDecision::GRANTED, OrganizationAccessDecision $equipment = OrganizationAccessDecision::GRANTED, string $querySuffix = ''): ParkAnomaliesProvider
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturnMap([
      ['user', 'org', 'organization.inspection.read', $inspection],
      ['user', 'org', 'organization.equipment.read', $equipment],
    ]);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('user', 'user@example.com', 'unused', ['ROLE_USER']));
    $requests = new RequestStack();
    $requests->push(Request::create('/anomalies?family=fire&customerId=customer&facilityId=facility&page=2&itemsPerPage=2' . $querySuffix));

    return new ParkAnomaliesProvider($queries, new ParkAnomaliesAccess($security, $authorization), $requests);
  }
}
