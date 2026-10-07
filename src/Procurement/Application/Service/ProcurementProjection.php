<?php

declare(strict_types=1);

namespace Procurement\Application\Service;

use Procurement\Application\Contract\{ProcurementReceiptState, ProcurementReturnState};
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\ProcurementLine;

use const DATE_ATOM;

/** Creates authorized transport-neutral snapshots without exposing hidden internal amounts. */
final readonly class ProcurementProjection
{
  /**
   * @return array<string,mixed>
   */
  public function supplier(Supplier $supplier): array
  {
    return ['id' => $supplier->id, 'organizationId' => $supplier->organizationId, 'name' => $supplier->name(), 'code' => $supplier->code(), 'email' => $supplier->email(), 'phone' => $supplier->phone(), 'contacts' => $supplier->contacts(), 'archivedAt' => $supplier->archivedAt()?->format(DATE_ATOM), 'createdAt' => $supplier->createdAt->format(DATE_ATOM), 'updatedAt' => $supplier->updatedAt()->format(DATE_ATOM), 'revision' => $supplier->revision()];
  }

  /**
   * @return array<string,mixed>
   */
  public function order(PurchaseOrder $order, bool $financialVisible): array
  {
    $lines = [];
    foreach ($order->lines() as $line) {
      $lines[] = $this->line($line, $financialVisible);
    }

    return ['id' => $order->id, 'organizationId' => $order->organizationId, 'supplierId' => $order->supplierId(), 'name' => $order->name(), 'currency' => $order->currency(), 'status' => $order->status()->value, 'lines' => $lines, 'financialVisible' => $financialVisible, 'revision' => $order->revision(), 'createdAt' => $order->createdAt->format(DATE_ATOM), 'updatedAt' => $order->updatedAt()->format(DATE_ATOM)];
  }

  /**
   * @return array<string,mixed>
   */
  public function receipt(ProcurementReceiptState $receipt, bool $financialVisible): array
  {
    $data = ['id' => $receipt->id, 'organizationId' => $receipt->organizationId, 'orderId' => $receipt->orderId, 'lineId' => $receipt->lineId, 'kind' => $receipt->kind, 'quantity' => $receipt->quantity, 'warehouseId' => $receipt->warehouseId, 'currency' => $receipt->currency, 'receivedAt' => $receipt->receivedAt->format(DATE_ATOM), 'createdAt' => $receipt->createdAt->format(DATE_ATOM), 'inventoryMovementId' => $receipt->inventoryMovementId, 'equipmentIds' => $receipt->equipmentIds, 'returnedQuantity' => $receipt->returnedQuantity, 'status' => $receipt->status(), 'blockedReason' => $receipt->blockedReason, 'revision' => $receipt->revision, 'financialVisible' => $financialVisible];
    if ($financialVisible) {
      $data['unitCost'] = $receipt->unitCost;
    }
    $data['pendingReturnQuantity'] = $receipt->pendingReturnQuantity;

    return $data;
  }

  /**
   * @return array<string,mixed>
   */
  public function returnDeclaration(ProcurementReturnState $return): array
  {
    return ['id' => $return->id, 'organizationId' => $return->organizationId, 'receiptId' => $return->receiptId, 'clientOperationId' => $return->clientOperationId, 'quantity' => $return->quantity, 'reason' => $return->reason, 'status' => $return->status, 'inventoryMovementId' => $return->inventoryMovementId, 'blockedReason' => $return->blockedReason, 'createdAt' => $return->createdAt->format(DATE_ATOM), 'reconciledAt' => $return->reconciledAt?->format(DATE_ATOM), 'revision' => $return->revision];
  }

  /**
   * @return array<string,mixed>
   */
  private function line(ProcurementLine $line, bool $financialVisible): array
  {
    $data = ['id' => $line->id, 'kind' => $line->kind, 'partId' => $line->partId, 'typeCode' => $line->typeCode, 'identityTemplate' => $line->identityTemplate, 'quantity' => $line->quantity, 'receivedQuantity' => $line->receivedQuantity, 'returnedQuantity' => $line->returnedQuantity, 'remainingQuantity' => $line->remainingQuantity()];
    if ('part' === $line->kind) {
      $data['partCode'] = $line->partCode;
      $data['partLabel'] = $line->partLabel;
      $data['partUnit'] = $line->partUnit;
    }
    if ($financialVisible) {
      $data['unitCost'] = $line->unitCost;
    }

    return $data;
  }
}
