<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Presentation;

use ApiPlatform\Metadata\GetCollection;
use Auth\Infrastructure\Security\User\SecurityUser;
use Inventory\Application\UseCase\Query\ListInventory\{ListInventoryQuery,ListInventoryResult};
use Inventory\Domain\Model\Stock\StockBalance;
use Inventory\Presentation\Api\Provider\InventoryProvider;
use Inventory\Presentation\Api\Service\{InventoryAccess,InventoryOutputFactory};
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request,RequestStack};
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

use function iterator_to_array;

/** Permission-specific quantity projections do not expose internal values. @category Unit Tests */
final class InventoryProviderTest extends TestCase
{
  private const string ORG = 'beb70000-0000-4000-8000-000000000001';

  #[Test]
  public function quantityReaderKeepsPaginationAndFiltersWithoutReceivingValue(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->willReturnCallback(static function (ListInventoryQuery $q): ListInventoryResult {
      self::assertSame(2, $q->page);
      self::assertSame(5, $q->itemsPerPage);
      self::assertSame(['partId' => 'beb70000-0000-4000-8000-000000000002'], $q->filters);

      return new ListInventoryResult([new StockBalance('balance', self::ORG, 'part', 'warehouse', '1.000000', '75.000000', 'EUR')], 6);
    });
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturnCallback(static fn (string $actor, string $org, string $permission): OrganizationAccessDecision => 'organization.maintenance_cost.read' === $permission ? OrganizationAccessDecision::MISSING_PERMISSION : OrganizationAccessDecision::GRANTED);
    $stack = new RequestStack();
    $stack->push(new Request(['page' => '2', 'itemsPerPage' => '5', 'partId' => 'beb70000-0000-4000-8000-000000000002']));
    $provider = new InventoryProvider($queries, $this->access($auth), new InventoryOutputFactory(), $stack);
    $page = $provider->provide(new GetCollection(name:'inventory_balances_list'), ['organizationId' => self::ORG]);
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $page);
    self::assertSame(6.0, $page->getTotalItems());
    $items = iterator_to_array($page);
    self::assertCount(1, $items);
    self::assertInstanceOf(\Inventory\Presentation\Api\Dto\Output\InventoryBalanceOutput::class, $items[0]);
    self::assertNull($items[0]->valuation);
  }

  #[Test]
  public function consumptionOperationFilterRemainsBoundToTheAuthorizedOrganization(): void
  {
    $operationId = 'beb70000-0000-4000-8000-000000000004';
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::once())->method('ask')->willReturnCallback(static function (ListInventoryQuery $query) use ($operationId): ListInventoryResult {
      self::assertSame(self::ORG, $query->organizationId);
      self::assertSame('consumptions', $query->type);
      self::assertSame(['clientOperationId' => $operationId], $query->filters);

      return new ListInventoryResult([], 0);
    });
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    $requests = new RequestStack();
    $requests->push(new Request(['clientOperationId' => $operationId]));
    $provider = new InventoryProvider($queries, $this->access($authorization), new InventoryOutputFactory(), $requests);
    $page = $provider->provide(new GetCollection(name: 'inventory_consumptions_list'), ['organizationId' => self::ORG]);
    self::assertInstanceOf(\ApiPlatform\State\Pagination\TraversablePaginator::class, $page);
    self::assertSame(0.0, $page->getTotalItems());
  }

  #[Test]
  public function missingReadPermissionStopsBeforeAnyInventoryQuery(): void
  {
    $queries = $this->createMock(QueryBusPort::class);
    $queries->expects(self::never())->method('ask');
    $auth = $this->createStub(OrganizationAuthorizationPort::class);
    $auth->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);
    $provider = new InventoryProvider($queries, $this->access($auth), new InventoryOutputFactory(), new RequestStack());
    $this->expectException(AccessDeniedHttpException::class);
    $provider->provide(new GetCollection(name:'inventory_parts_list'), ['organizationId' => self::ORG]);
  }

  private function access(OrganizationAuthorizationPort $auth): InventoryAccess
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(new SecurityUser('beb70000-0000-4000-8000-000000000003', 'stock@corp.example', 'unused', ['ROLE_USER']));

    return new InventoryAccess($security, $auth);
  }
}
