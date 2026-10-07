<?php

declare(strict_types=1);

namespace Inventory\Application\UseCase\Query\ListInventory;

use Intervention\Application\Port\Inbound\InterventionInventoryContextPort;
use InvalidArgumentException;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Exception\InventoryNotFoundException;
use Shared\Application\Message\QueryHandler;
use Shared\Domain\ValueObject\Uuid;

use function in_array;
use function mb_strlen;

/** @category UseCase */
final readonly class ListInventoryHandler implements QueryHandler
{
  public function __construct(private InventoryStorePort $store, private InterventionInventoryContextPort $interventions)
  {
  }

  public function __invoke(ListInventoryQuery $query): ListInventoryResult
  {
    new Uuid($query->organizationId);
    if (isset($query->filters['archived']) && !in_array($query->filters['archived'], ['true', 'false'], true)) {
      throw new InvalidArgumentException('archived must be true or false.');
    }
    if (isset($query->filters['search']) && mb_strlen($query->filters['search']) > 255) {
      throw new InvalidArgumentException('Search must contain at most 255 characters.');
    }
    if (isset($query->filters['status']) && !in_array($query->filters['status'], ['confirmed', 'received_pending'], true)) {
      throw new InvalidArgumentException('Unknown consumption status.');
    }
    if ($query->page < 1 || $query->itemsPerPage < 1 || $query->itemsPerPage > 100) {
      throw new InvalidArgumentException('Inventory pagination must be between 1 and 100.');
    }
    foreach (['warehouseId' => 'warehouses', 'partId' => 'parts'] as $key => $type) {
      if (isset($query->filters[$key])) {
        new Uuid($query->filters[$key]);
        if (null === $this->store->reference($type, $query->organizationId, $query->filters[$key])) {
          throw new InventoryNotFoundException('Inventory reference not found.');
        }
      }
    }
    if (null !== $query->id) {
      new Uuid($query->id);
      $item = match($query->type) {
        'parts','warehouses' => $this->store->reference($query->type, $query->organizationId, $query->id),'consumptions' => $this->store->declaration($query->organizationId, $query->id),'movements' => $this->store->movement($query->organizationId, $query->id),default => throw new InvalidArgumentException('Unsupported inventory detail.')
      };
      if (null === $item) {
        throw new InventoryNotFoundException('Inventory item not found.');
      }

      return new ListInventoryResult([$item], 1);
    }
    if (isset($query->filters['interventionId'])) {
      new Uuid($query->filters['interventionId']);
      if (!$this->interventions->existsInOrganization($query->organizationId, $query->filters['interventionId'])) {
        throw new InventoryNotFoundException('Intervention not found.');
      }
    }

    return new ListInventoryResult($this->store->list($query->type, $query->organizationId, $query->filters, $query->itemsPerPage, ($query->page - 1) * $query->itemsPerPage), $this->store->count($query->type, $query->organizationId, $query->filters));
  }
}
