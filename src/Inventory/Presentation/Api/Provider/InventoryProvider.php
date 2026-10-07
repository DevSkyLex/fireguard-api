<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use ApiPlatform\State\ProviderInterface;
use ArrayIterator;
use Inventory\Application\UseCase\Query\ListInventory\{ListInventoryQuery,ListInventoryResult};
use Inventory\Presentation\Api\Dto\Output\{InventoryBalanceOutput, InventoryConsumptionOutput, InventoryMovementOutput, InventoryPartOutput, InventoryWarehouseOutput};
use Inventory\Presentation\Api\Service\{InventoryAccess,InventoryOutputFactory};
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

use function array_map;
use function explode;
use function is_string;
use function max;

/**
 * @category Provider
 *
 * @implements ProviderInterface<InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput>
 */
final readonly class InventoryProvider implements ProviderInterface
{
  public function __construct(private QueryBusPort $queries, private InventoryAccess $access, private InventoryOutputFactory $outputs, private RequestStack $requests)
  {
  }

  /**
   * @param array<string,mixed> $uriVariables the route scope
   * @param array<string,mixed> $context the provider context
   *
   * @return InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput|TraversablePaginator<InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput> inventory projection
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): object
  {
    $org = $this->access->organization($uriVariables, 'organization.inventory.read');
    $name = $operation->getName() ?? '';
    $type = explode('_', $name)[1] ?? '';
    $request = $this->requests->getCurrentRequest();
    $filters = [];
    $allowed = match($type) {
      'parts','warehouses' => ['search', 'archived'],'balances' => ['warehouseId', 'partId'],'movements' => ['warehouseId', 'partId', 'interventionId'],'consumptions' => ['warehouseId', 'partId', 'interventionId', 'status'],default => throw new BadRequestHttpException('Invalid inventory collection.')
    };
    foreach ($allowed as $key) {
      $value = $request?->query->get($key);
      if (null !== $value) {
        if ('' === $value) {
          throw new BadRequestHttpException('Invalid inventory filter.');
        }$filters[$key] = $value;
      }
    }
    $page = max(1, $request?->query->getInt('page', 1) ?? 1);
    $size = $request?->query->getInt('itemsPerPage', 30) ?? 30;
    $id = $uriVariables['id'] ?? null;
    if (null !== $id && !is_string($id)) {
      throw new BadRequestHttpException('Invalid inventory identifier.');
    }
    /** @var ListInventoryResult $result */
    $result = $this->queries->ask(new ListInventoryQuery($org, $type, $filters, $page, $size, $id));
    $finance = $this->access->financial($org);
    $items = array_map(fn ($item) => $this->outputs->output($item, $finance), $result->items);
    if (null !== $id) {
      return $items[0];
    }

    return new TraversablePaginator(new ArrayIterator($items), (float) $page, (float) $size, (float) $result->totalItems);
  }
}
