<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Presentation\Api\Reporting;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Intervention\Application\Contract\Publication\InterventionEconomicContextPage;
use MaintenanceCost\Application\UseCase\Query\Reporting\ListMaintenanceEconomicDossiers\{ListMaintenanceEconomicDossiersQuery, ListMaintenanceEconomicDossiersResult};
use MaintenanceCost\Presentation\Api\Operation\Reporting\MaintenanceEconomicOperations;
use MaintenanceCost\Presentation\Api\Provider\Reporting\MaintenanceEconomicProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Class MaintenanceEconomicProviderTest
 *
 * Pagination and contextual filters cross the bus without exposing ordinary dossiers.
 *
 * @category Test
 */
final class MaintenanceEconomicProviderTest extends TestCase
{
  public function testDirectoryCarriesActorAndServerFiltersToTheAuthorizedQuery(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $requests = new RequestStack();
    $requests->push(Request::create('/dossiers?search=Valve&page=2&itemsPerPage=10&siteId=site&customerId=customer&equipmentId=equipment&from=2026-10-01&to=2026-10-31'));
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->with(self::callback(static fn (ListMaintenanceEconomicDossiersQuery $query): bool => 'actor' === $query->actorId && 'org' === $query->organizationId && 2 === $query->page && 10 === $query->itemsPerPage && 'Valve' === $query->search && 'site' === $query->siteId && 'customer' === $query->customerId && 'equipment' === $query->equipmentId && '2026-10-01' === $query->from && '2026-10-31' === $query->to))->willReturn(new ListMaintenanceEconomicDossiersResult(new InterventionEconomicContextPage([], 15, 2, 10)));
    $output = new MaintenanceEconomicProvider($queries, $actor, $requests)->provide(new GetCollection(name: MaintenanceEconomicOperations::DOSSIERS), ['organizationId' => 'org']);
    self::assertInstanceOf(TraversablePaginator::class, $output);
    self::assertSame(15.0, $output->getTotalItems());
    self::assertSame(2.0, $output->getCurrentPage());
    self::assertSame(10.0, $output->getItemsPerPage());
  }

  public function testUnauthenticatedDirectoryNeverDispatchesItsQuery(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn(null);
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $this->expectException(AccessDeniedHttpException::class);
    new MaintenanceEconomicProvider($queries, $actor, new RequestStack())->provide(new GetCollection(name: MaintenanceEconomicOperations::DOSSIERS), ['organizationId' => 'org']);
  }
}
