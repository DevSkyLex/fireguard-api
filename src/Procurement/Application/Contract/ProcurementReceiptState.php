<?php

declare(strict_types=1);

namespace Procurement\Application\Contract;

use DateTimeImmutable;

/** Immutable physical receipt identity with tracked stock and equipment reconciliation. */
final class ProcurementReceiptState
{
  /**
   * @param list<string> $equipmentIds
   */
  public function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    public readonly string $orderId,
    public readonly string $lineId,
    public readonly string $kind,
    public readonly string $quantity,
    public readonly ?string $warehouseId,
    public readonly ?string $unitCost,
    public readonly string $currency,
    public readonly DateTimeImmutable $receivedAt,
    public readonly string $actorId,
    public readonly DateTimeImmutable $createdAt,
    public ?string $inventoryMovementId = null,
    public array $equipmentIds = [],
    public string $returnedQuantity = '0.000000',
    public ?string $blockedReason = null,
    public int $revision = 1,
    public string $pendingReturnQuantity = '0.000000',
  ) {
  }

  public function status(): string
  {
    if ($this->quantity === $this->returnedQuantity) {
      return 'returned';
    }

    if ('part' === $this->kind) {
      return 'stock_received';
    }

    return [] === $this->equipmentIds ? 'awaiting_individualization' : 'individualized';
  }
}
