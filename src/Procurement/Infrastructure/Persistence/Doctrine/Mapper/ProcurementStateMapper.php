<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementGoodsIdentity, ProcurementLineAmounts, PurchaseOrderHistory, PurchaseOrderIdentity, PurchaseOrderLines, SupplierDetails, SupplierHistory};
use Procurement\Domain\ValueObject\{ProcurementLine, PurchaseOrderStatus};

use function get_object_vars;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/**
 * Class ProcurementStateMapper
 *
 * Translates exact retained state without owning queries, locks or transactions.
 *
 * @category Mapper
 */
final readonly class ProcurementStateMapper
{
  // #region Methods
  /**
   * Method supplierToRow
   *
   * @access public
   *
   * @param Supplier $supplier the retained state
   *
   * @return array<string,mixed> the persisted column values
   */
  public function supplierToRow(Supplier $supplier): array
  {
    return ['id' => $supplier->id, 'organization_id' => $supplier->organizationId, 'name' => $supplier->name(), 'code' => $supplier->code(), 'email' => $supplier->email(), 'phone' => $supplier->phone(), 'contacts' => $this->json($supplier->contacts()), 'archived_at' => $this->time($supplier->archivedAt()), 'created_at' => $this->time($supplier->createdAt), 'updated_at' => $this->time($supplier->updatedAt()), 'revision' => $supplier->revision()];
  }

  /**
   * Method orderToRow
   *
   * @access public
   *
   * @param PurchaseOrder $order the retained state
   *
   * @return array<string,mixed> the persisted column values
   */
  public function orderToRow(PurchaseOrder $order): array
  {
    $lines = [];
    foreach ($order->lines() as $line) {
      $lines[] = get_object_vars($line);
    }

    return ['id' => $order->id, 'organization_id' => $order->organizationId, 'supplier_id' => $order->supplierId(), 'name' => $order->name(), 'currency' => $order->currency(), 'status' => $order->status()->value, 'lines' => $this->json($lines), 'revision' => $order->revision(), 'created_at' => $this->time($order->createdAt), 'updated_at' => $this->time($order->updatedAt())];
  }

  /**
   * Method receiptToRow
   *
   * @access public
   *
   * @param ProcurementReceiptState $receipt the retained state
   *
   * @return array<string,mixed> the persisted column values
   */
  public function receiptToRow(ProcurementReceiptState $receipt): array
  {
    return ['id' => $receipt->id, 'organization_id' => $receipt->organizationId, 'order_id' => $receipt->orderId, 'line_id' => $receipt->lineId, 'kind' => $receipt->kind, 'quantity' => $receipt->quantity, 'warehouse_id' => $receipt->warehouseId, 'unit_cost' => $receipt->unitCost, 'currency' => $receipt->currency, 'received_at' => $this->time($receipt->receivedAt), 'actor_id' => $receipt->actorId, 'created_at' => $this->time($receipt->createdAt), 'inventory_movement_id' => $receipt->inventoryMovementId, 'equipment_ids' => $this->json($receipt->equipmentIds), 'returned_quantity' => $receipt->returnedQuantity, 'pending_return_quantity' => $receipt->pendingReturnQuantity, 'blocked_reason' => $receipt->blockedReason, 'revision' => $receipt->revision];
  }

  /**
   * Method returnToRow
   *
   * @access public
   *
   * @param ProcurementReturnState $return the retained state
   *
   * @return array<string,mixed> the persisted column values
   */
  public function returnToRow(ProcurementReturnState $return): array
  {
    return ['id' => $return->id, 'organization_id' => $return->organizationId, 'receipt_id' => $return->receiptId, 'client_operation_id' => $return->clientOperationId, 'quantity' => $return->quantity, 'reason' => $return->reason, 'actor_id' => $return->actorId, 'created_at' => $this->time($return->createdAt), 'status' => $return->status, 'inventory_movement_id' => $return->inventoryMovementId, 'blocked_reason' => $return->blockedReason, 'reconciled_at' => $this->time($return->reconciledAt), 'revision' => $return->revision];
  }

  /**
   * Method operationFromRow
   *
   * @access public
   *
   * @param array<string,mixed> $row the persisted operation columns
   *
   * @return ProcurementOperationState the retained replay identity
   */
  public function operationFromRow(array $row): ProcurementOperationState
  {
    /** @var array{organization_id:string,client_operation_id:string,kind:string,fingerprint:string,receipt_id:string,declaration:string} $row */
    /** @var array<string,mixed> $declaration */
    $declaration = json_decode($row['declaration'], true, 512, JSON_THROW_ON_ERROR);

    return new ProcurementOperationState($row['organization_id'], $row['client_operation_id'], $row['kind'], $row['fingerprint'], $row['receipt_id'], $declaration);
  }

  /**
   * Method operationToRow
   *
   * @access public
   *
   * @param ProcurementOperationState $operation the retained replay identity
   *
   * @return array<string,mixed> the persisted column values
   */
  public function operationToRow(ProcurementOperationState $operation): array
  {
    return ['organization_id' => $operation->organizationId, 'client_operation_id' => $operation->clientOperationId, 'kind' => $operation->kind, 'fingerprint' => $operation->fingerprint, 'receipt_id' => $operation->receiptId, 'declaration' => $this->json($operation->declaration)];
  }

  /**
   * Method supplierFromRow
   *
   * Restores validated supplier contact data and its exact lifecycle history.
   *
   * @access public
   *
   * @param array<string,mixed> $row
   *
   * @return Supplier
   */
  public function supplierFromRow(array $row): Supplier
  {
    /** @var array{id:string,organization_id:string,name:string,code:?string,email:?string,phone:?string,contacts:string,archived_at:?string,created_at:string,updated_at:string,revision:int|string} $row */
    /** @var list<array{name:string,email:?string,phone:?string,role:?string}> $contacts */
    $contacts = json_decode($row['contacts'], true, 512, JSON_THROW_ON_ERROR);

    return Supplier::reconstitute($row['id'], $row['organization_id'], new SupplierDetails($row['name'], $row['code'], $row['email'], $row['phone'], $contacts), new SupplierHistory($this->dateOrNull($row['archived_at']), $this->date($row['created_at']), $this->date($row['updated_at']), (int) $row['revision']));
  }

  /**
   * Method orderFromRow
   *
   * Restores ordered catalog snapshots and their retained gross receipt lifecycle.
   *
   * @access public
   *
   * @param array<string,mixed> $row
   *
   * @return PurchaseOrder
   */
  public function orderFromRow(array $row): PurchaseOrder
  {
    /** @var array{id:string,organization_id:string,supplier_id:string,currency:string,name:string,lines:string,status:string,revision:int|string,created_at:string,updated_at:string} $row */
    /** @var list<array{id:string,kind:string,partId:?string,typeCode:?string,identityTemplate:array<string,mixed>,quantity:string,unitCost:?string,receivedQuantity:string,returnedQuantity:string,partCode?:?string,partLabel?:?string,partUnit?:?string}> $stored */
    $stored = json_decode($row['lines'], true, 512, JSON_THROW_ON_ERROR);
    $lines = [];
    foreach ($stored as $line) {
      $lines[] = ProcurementLine::reconstitute($line['id'], new ProcurementGoodsIdentity($line['kind'], $line['partId'], $line['typeCode'], $line['identityTemplate'], $line['partCode'] ?? null, $line['partLabel'] ?? null, $line['partUnit'] ?? null), new ProcurementLineAmounts($line['quantity'], $line['unitCost'], $line['receivedQuantity'], $line['returnedQuantity']));
    }

    return PurchaseOrder::reconstitute($row['id'], $row['organization_id'], new PurchaseOrderIdentity($row['supplier_id'], $row['currency'], $row['name']), new PurchaseOrderLines($lines), new PurchaseOrderHistory(PurchaseOrderStatus::from($row['status']), (int) $row['revision'], $this->date($row['created_at']), $this->date($row['updated_at'])));
  }

  /**
   * Method receiptFromRow
   *
   * Restores immutable physical receipt identity and mutable reconciliation state.
   *
   * @access public
   *
   * @param array<string,mixed> $row
   *
   * @return ProcurementReceiptState
   */
  public function receiptFromRow(array $row): ProcurementReceiptState
  {
    /** @var array{id:string,organization_id:string,order_id:string,line_id:string,kind:string,quantity:string,warehouse_id:?string,unit_cost:?string,currency:string,received_at:string,actor_id:string,created_at:string,inventory_movement_id:?string,equipment_ids:string,returned_quantity:string,pending_return_quantity:string,blocked_reason:?string,revision:int|string} $row */
    /** @var list<string> $equipmentIds */
    $equipmentIds = json_decode($row['equipment_ids'], true, 512, JSON_THROW_ON_ERROR);

    return new ProcurementReceiptState($row['id'], $row['organization_id'], $row['order_id'], $row['line_id'], $row['kind'], $row['quantity'], $row['warehouse_id'], $row['unit_cost'], $row['currency'], $this->date($row['received_at']), $row['actor_id'], $this->date($row['created_at']), $row['inventory_movement_id'], $equipmentIds, $row['returned_quantity'], $row['blocked_reason'], (int) $row['revision'], $row['pending_return_quantity']);
  }

  /**
   * Method returnFromRow
   *
   * Restores retained return evidence and its reconciliation state.
   *
   * @access public
   *
   * @param array<string,mixed> $row
   *
   * @return ProcurementReturnState
   */
  public function returnFromRow(array $row): ProcurementReturnState
  {
    /** @var array{id:string,organization_id:string,receipt_id:string,client_operation_id:string,quantity:string,reason:string,actor_id:string,created_at:string,status:string,inventory_movement_id:?string,blocked_reason:?string,reconciled_at:?string,revision:int|string} $row */

    return new ProcurementReturnState($row['id'], $row['organization_id'], $row['receipt_id'], $row['client_operation_id'], $row['quantity'], $row['reason'], $row['actor_id'], $this->date($row['created_at']), $row['status'], $row['inventory_movement_id'], $row['blocked_reason'], $this->dateOrNull($row['reconciled_at']), (int) $row['revision']);
  }

  /**
   * Method time
   *
   * Persists nullable instants in the existing UTC timestamp representation.
   *
   * @access private
   *
   * @param ?DateTimeImmutable $time the time value
   *
   * @return ?string
   */
  private function time(?DateTimeImmutable $time): ?string
  {
    return $time?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  /**
   * Method date
   *
   * Requires a valid physical timestamp with an explicit offset.
   *
   * @access private
   *
   * @param string $value the value value
   *
   * @return DateTimeImmutable
   */
  private function date(string $value): DateTimeImmutable
  {
    return new DateTimeImmutable($value, new DateTimeZone('UTC'));
  }

  /**
   * Method dateOrNull
   *
   * Restores an optional retained instant without inventing a timestamp.
   *
   * @access private
   *
   * @param ?string $value the value value
   *
   * @return ?DateTimeImmutable
   */
  private function dateOrNull(?string $value): ?DateTimeImmutable
  {
    return null === $value ? null : $this->date($value);
  }

  /**
   * Method json
   *
   * Uses the existing PHP JSON representation for persisted declarative values.
   *
   * @access private
   *
   * @param array<array-key,mixed> $value
   *
   * @return string
   */
  private function json(array $value): string
  {
    return json_encode($value, JSON_THROW_ON_ERROR);
  }
  // #endregion
}
