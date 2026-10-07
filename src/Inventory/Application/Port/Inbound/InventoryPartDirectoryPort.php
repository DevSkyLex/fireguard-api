<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Inbound;

/** @category Port */
interface InventoryPartDirectoryPort
{
  /**
   * @param list<string> $partIds at most one hundred scoped identities
   *
   * @return array<string,\Inventory\Application\Contract\Directory\InventoryPartDescriptor> current nonfinancial descriptors
   */
  public function describeMany(string $organizationId, array $partIds): array;

  public function existsActive(string $organizationId, string $partId): bool;

  public function warehouseExistsActive(string $organizationId, string $warehouseId): bool;
}
