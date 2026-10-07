<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Adapter\Procurement;

use InvalidArgumentException;
use Inventory\Application\Port\Inbound\InventoryPartDirectoryPort;
use Inventory\Application\Port\Outbound\InventoryStorePort;

use function count;

/** Scope-safe internal catalog validation. @category Adapter */
final readonly class InventoryPartDirectoryAdapter implements InventoryPartDirectoryPort
{
  public function __construct(private InventoryStorePort $store)
  {
  }

  public function describeMany(string $organizationId, array $partIds): array
  {
    if (count($partIds) > 100) {
      throw new InvalidArgumentException('Part descriptor batches contain at most one hundred identities.');
    }
    $descriptors = [];
    foreach ($this->store->referencesByIds('parts', $organizationId, $partIds) as $id => $part) {
      $descriptors[$id] = new \Inventory\Application\Contract\Directory\InventoryPartDescriptor($part->id, $part->code, $part->label, $part->unit ?? 'piece', $part->archived);
    }

    return $descriptors;
  }

  public function existsActive(string $organizationId, string $partId): bool
  {
    $part = $this->store->reference('parts', $organizationId, $partId);

    return null !== $part && !$part->archived;
  }

  public function warehouseExistsActive(string $organizationId, string $warehouseId): bool
  {
    $w = $this->store->reference('warehouses', $organizationId, $warehouseId);

    return null !== $w && !$w->archived;
  }
}
