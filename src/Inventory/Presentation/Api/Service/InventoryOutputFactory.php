<?php

declare(strict_types=1);

namespace Inventory\Presentation\Api\Service;

use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use Inventory\Presentation\Api\Dto\Output\{InventoryBalanceOutput, InventoryConsumptionOutput, InventoryMovementOutput, InventoryPartOutput, InventoryValuationOutput, InventoryWarehouseOutput};
use Shared\Domain\ValueObject\DecimalAmount;

/** Builds quantity projections; internal values are only attached after the dedicated read decision. @category Service */
final readonly class InventoryOutputFactory
{
  public function output(InventoryReference|StockBalance|StockMovement|ConsumptionDeclaration $item, bool $financial = false, bool $replayed = false): InventoryPartOutput|InventoryWarehouseOutput|InventoryBalanceOutput|InventoryMovementOutput|InventoryConsumptionOutput
  {
    if ($item instanceof InventoryReference) {
      if (null === $item->kind) {
        $o = new InventoryWarehouseOutput();
        $o->name = $item->label;
      } else {
        $o = new InventoryPartOutput();
        $o->label = $item->label;
        $o->unit = $item->unit ?? 'piece';
        $o->kind = $item->kind;
      }
      $o->id = $item->id;
      $o->code = $item->code;
      $o->archived = $item->archived;

      return $o;
    }
    if ($item instanceof StockBalance) {
      $o = new InventoryBalanceOutput();
      $o->id = $item->id;
      $o->partId = $item->partId;
      $o->warehouseId = $item->warehouseId;
      $o->quantity = $item->quantity;
      if ($financial) {
        $o->valuation = $this->valuation(null === $item->totalValue || '0.000000' === $item->quantity ? null : DecimalAmount::fromString($item->totalValue)->divide(DecimalAmount::fromString($item->quantity))->toString(), $item->totalValue, $item->currency);
      }

      return $o;
    }
    if ($item instanceof ConsumptionDeclaration) {
      $o = new InventoryConsumptionOutput();
      $o->status = $item->status;
      $o->movementId = $item->movementId;
    } else {
      $o = new InventoryMovementOutput();
      $o->kind = $item->kind;
      $o->correctionOf = $item->correctionOf;
      $o->sourceReceiptId = $item->sourceReceiptId;
      if ($financial) {
        $o->valuation = $this->valuation($item->unitCost, $item->totalValue, $item->currency);
      }
    }
    $o->id = $item->id;
    $o->partId = $item->partId;
    $o->warehouseId = $item->warehouseId;
    $o->quantity = $item->quantity;
    $o->reason = $item->reason;
    $o->actorId = $item->actorId;
    $o->occurredAt = $item->occurredAt->format('c');
    $o->interventionId = $item->interventionId;
    $o->workItemId = $item->workItemId;
    $o->equipmentId = $item->equipmentId;
    $o->late = $item->late;
    $o->replayed = $replayed;

    return $o;
  }

  private function valuation(?string $unit, ?string $total, string $currency): InventoryValuationOutput
  {
    $v = new InventoryValuationOutput();
    $v->unitCost = $unit;
    $v->totalValue = $total;
    $v->currency = $currency;
    $v->incomplete = null === $total;

    return $v;
  }
}
