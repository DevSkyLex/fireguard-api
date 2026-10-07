<?php

declare(strict_types=1);

namespace Procurement\Application\Contract;

use DateTimeImmutable;

/** Immutable physical return declaration with an independently reconciled stock movement. */
final class ProcurementReturnState
{
  public function __construct(
    public readonly string $id,
    public readonly string $organizationId,
    public readonly string $receiptId,
    public readonly string $clientOperationId,
    public readonly string $quantity,
    public readonly string $reason,
    public readonly string $actorId,
    public readonly DateTimeImmutable $createdAt,
    public string $status = 'awaiting_reconciliation',
    public ?string $inventoryMovementId = null,
    public ?string $blockedReason = null,
    public ?DateTimeImmutable $reconciledAt = null,
    public int $revision = 1,
  ) {
  }
}
