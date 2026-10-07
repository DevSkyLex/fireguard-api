<?php

declare(strict_types=1);

namespace Tests\Unit\MaintenanceCost\Presentation\Api\Rate;

use ApiPlatform\Metadata\{GetCollection, Post};
use MaintenanceCost\Application\Contract\Rate\MaintenanceRateSnapshot;
use MaintenanceCost\Application\UseCase\Command\Rate\CreateMaintenanceRate\{CreateMaintenanceRateCommand, CreateMaintenanceRateResult};
use MaintenanceCost\Application\UseCase\Query\Rate\ListMaintenanceRates\{ListMaintenanceRatesQuery, ListMaintenanceRatesResult};
use MaintenanceCost\Presentation\Api\Dto\Input\Rate\CreateMaintenanceRateInput;
use MaintenanceCost\Presentation\Api\Processor\Rate\CreateMaintenanceRateProcessor;
use MaintenanceCost\Presentation\Api\Provider\Rate\MaintenanceRateProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Application\Port\Outbound\CurrentActorPort;
use Symfony\Component\HttpFoundation\{Request, RequestStack};

use function iterator_to_array;

/** Rate transport retains decimal strings and the stable client request identity. */
final class MaintenanceRateTransportTest extends TestCase
{
  #[Test]
  public function createsAReplayAwareExactRateOutput(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $bus = $this->createMock(CommandBusPort::class);
    $bus->expects(self::once())->method('dispatch')->with(self::callback(static fn (CreateMaintenanceRateCommand $command): bool => 'org' === $command->organizationId && '42.000001' === $command->hourlyAmount && 'client' === $command->clientId))->willReturn(new CreateMaintenanceRateResult(new MaintenanceRateSnapshot('rate', 'member', '42.000001', 'EUR', '2026-01-01'), true));
    $input = new CreateMaintenanceRateInput();
    $input->memberId = 'member';
    $input->clientId = 'client';
    $input->hourlyAmount = '42.000001';
    $input->effectiveFrom = '2026-01-01';
    $output = new CreateMaintenanceRateProcessor($bus, $actor)->process($input, new Post(), ['organizationId' => 'org']);
    self::assertSame('42.000001', $output->hourlyAmount);
    self::assertTrue($output->replayed);
  }

  #[Test]
  public function paginatesRatesWithTheExplicitMemberFilter(): void
  {
    $actor = $this->createStub(CurrentActorPort::class);
    $actor->method('userId')->willReturn('actor');
    $requests = new RequestStack();
    $requests->push(Request::create('/rates?memberId=member&page=2&itemsPerPage=10'));
    $bus = $this->createMock(QueryBusPort::class);
    $bus->expects(self::once())->method('ask')->with(self::callback(static fn (ListMaintenanceRatesQuery $query): bool => 'org' === $query->organizationId && 'member' === $query->memberId && 2 === $query->page && 10 === $query->itemsPerPage))->willReturn(new ListMaintenanceRatesResult([new MaintenanceRateSnapshot('rate', 'member', '42.000001', 'EUR', '2026-01-01')], 11, 2, 10));
    $page = new MaintenanceRateProvider($bus, $actor, $requests)->provide(new GetCollection(), ['organizationId' => 'org']);
    self::assertSame(11.0, $page->getTotalItems());
    $rows = iterator_to_array($page);
    self::assertCount(1, $rows);
    self::assertSame('42.000001', $rows[0]->hourlyAmount);
  }
}
