<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{Connection, ParameterType};
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Domain\ValueObject\{ProcurementLine, PurchaseOrderStatus};

use function array_keys;
use function array_map;
use function get_object_vars;
use function implode;
use function in_array;
use function json_decode;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** Explicit-main PostgreSQL repository with scoped reads and serialized physical operations. */
final readonly class ProcurementRepository implements ProcurementRepositoryPort
{
  public function __construct(private Connection $connection)
  {
  }

  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'procurement.' . $organizationId]);

      return $work();
    });
  }

  public function supplier(string $organizationId, string $id): ?Supplier
  {
    $row = $this->one('procurement_suppliers', $organizationId, $id);

    return null === $row ? null : $this->supplierFromRow($row);
  }

  public function saveSupplier(Supplier $supplier): void
  {
    $this->upsert('procurement_suppliers', ['id' => $supplier->id, 'organization_id' => $supplier->organizationId, 'name' => $supplier->name(), 'code' => $supplier->code(), 'email' => $supplier->email(), 'phone' => $supplier->phone(), 'contacts' => $this->json($supplier->contacts()), 'archived_at' => $this->time($supplier->archivedAt()), 'created_at' => $this->time($supplier->createdAt), 'updated_at' => $this->time($supplier->updatedAt()), 'revision' => $supplier->revision()]);
  }

  public function order(string $organizationId, string $id): ?PurchaseOrder
  {
    $row = $this->one('procurement_orders', $organizationId, $id);

    return null === $row ? null : $this->orderFromRow($row);
  }

  public function saveOrder(PurchaseOrder $order): void
  {
    $lines = [];
    foreach ($order->lines() as $line) {
      $lines[] = get_object_vars($line);
    }
    $this->upsert('procurement_orders', ['id' => $order->id, 'organization_id' => $order->organizationId, 'supplier_id' => $order->supplierId(), 'name' => $order->name(), 'currency' => $order->currency(), 'status' => $order->status()->value, 'lines' => $this->json($lines), 'revision' => $order->revision(), 'created_at' => $this->time($order->createdAt), 'updated_at' => $this->time($order->updatedAt())]);
  }

  public function receipt(string $organizationId, string $id): ?ProcurementReceiptState
  {
    $row = $this->one('procurement_receipts', $organizationId, $id);

    return null === $row ? null : $this->receiptFromRow($row);
  }

  public function saveReceipt(ProcurementReceiptState $receipt): void
  {
    $this->upsert('procurement_receipts', ['id' => $receipt->id, 'organization_id' => $receipt->organizationId, 'order_id' => $receipt->orderId, 'line_id' => $receipt->lineId, 'kind' => $receipt->kind, 'quantity' => $receipt->quantity, 'warehouse_id' => $receipt->warehouseId, 'unit_cost' => $receipt->unitCost, 'currency' => $receipt->currency, 'received_at' => $this->time($receipt->receivedAt), 'actor_id' => $receipt->actorId, 'created_at' => $this->time($receipt->createdAt), 'inventory_movement_id' => $receipt->inventoryMovementId, 'equipment_ids' => $this->json($receipt->equipmentIds), 'returned_quantity' => $receipt->returnedQuantity, 'pending_return_quantity' => $receipt->pendingReturnQuantity, 'blocked_reason' => $receipt->blockedReason, 'revision' => $receipt->revision]);
  }

  public function returnDeclaration(string $organizationId, string $id): ?ProcurementReturnState
  {
    $row = $this->one('procurement_returns', $organizationId, $id);

    return null === $row ? null : $this->returnFromRow($row);
  }

  public function saveReturn(ProcurementReturnState $return): void
  {
    $this->upsert('procurement_returns', ['id' => $return->id, 'organization_id' => $return->organizationId, 'receipt_id' => $return->receiptId, 'client_operation_id' => $return->clientOperationId, 'quantity' => $return->quantity, 'reason' => $return->reason, 'actor_id' => $return->actorId, 'created_at' => $this->time($return->createdAt), 'status' => $return->status, 'inventory_movement_id' => $return->inventoryMovementId, 'blocked_reason' => $return->blockedReason, 'reconciled_at' => $this->time($return->reconciledAt), 'revision' => $return->revision]);
  }

  public function returns(string $organizationId, string $receiptId, int $offset, int $limit): array
  {
    return array_map($this->returnFromRow(...), $this->page('procurement_returns', 'organization_id = :org AND receipt_id = :receipt', ['org' => $organizationId, 'receipt' => $receiptId], $offset, $limit, 'created_at, id'));
  }

  public function countReturns(string $organizationId, string $receiptId): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_returns WHERE organization_id = :org AND receipt_id = :receipt', ['org' => $organizationId, 'receipt' => $receiptId]);

    return (int) $count;
  }

  public function operation(string $organizationId, string $clientOperationId): ?ProcurementOperationState
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM procurement_operations WHERE organization_id = :org AND client_operation_id = :operation', ['org' => $organizationId, 'operation' => $clientOperationId]);
    if (false === $row) {
      return null;
    }
    /** @var array{organization_id:string,client_operation_id:string,kind:string,fingerprint:string,receipt_id:string,declaration:string} $row */
    /** @var array<string,mixed> $declaration */
    $declaration = json_decode($row['declaration'], true, 512, JSON_THROW_ON_ERROR);

    return new ProcurementOperationState($row['organization_id'], $row['client_operation_id'], $row['kind'], $row['fingerprint'], $row['receipt_id'], $declaration);
  }

  public function saveOperation(ProcurementOperationState $operation): void
  {
    $existing = $this->operation($operation->organizationId, $operation->clientOperationId);
    if (null !== $existing) {
      if ($existing->kind !== $operation->kind || $existing->fingerprint !== $operation->fingerprint || $existing->receiptId !== $operation->receiptId) {
        throw ProcurementException::conflict('The retained operation key cannot be reused.');
      }

      return;
    }
    $this->connection->insert('procurement_operations', ['organization_id' => $operation->organizationId, 'client_operation_id' => $operation->clientOperationId, 'kind' => $operation->kind, 'fingerprint' => $operation->fingerprint, 'receipt_id' => $operation->receiptId, 'declaration' => $this->json($operation->declaration)]);
  }

  public function suppliers(string $organizationId, string $search, ?bool $archived, int $offset, int $limit): array
  {
    $criteria = $this->supplierCriteria($archived);
    $rows = $this->page('procurement_suppliers', $criteria, ['org' => $organizationId, 'search' => '%' . $search . '%'], $offset, $limit, 'LOWER(name), id');

    return array_map($this->supplierFromRow(...), $rows);
  }

  public function countSuppliers(string $organizationId, string $search, ?bool $archived): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_suppliers WHERE ' . $this->supplierCriteria($archived), ['org' => $organizationId, 'search' => '%' . $search . '%']);

    return (int) $count;
  }

  public function orders(string $organizationId, ?string $status, ?string $supplierId, int $offset, int $limit): array
  {
    [$criteria, $parameters] = $this->orderCriteria($organizationId, $status, $supplierId);

    return array_map($this->orderFromRow(...), $this->page('procurement_orders', $criteria, $parameters, $offset, $limit, 'created_at DESC, id'));
  }

  public function countOrders(string $organizationId, ?string $status, ?string $supplierId): int
  {
    [$criteria, $parameters] = $this->orderCriteria($organizationId, $status, $supplierId);

    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_orders WHERE ' . $criteria, $parameters);

    return (int) $count;
  }

  public function receipts(string $organizationId, string $orderId, int $offset, int $limit): array
  {
    return array_map($this->receiptFromRow(...), $this->page('procurement_receipts', 'organization_id = :org AND order_id = :order', ['org' => $organizationId, 'order' => $orderId], $offset, $limit, 'created_at, id'));
  }

  public function countReceipts(string $organizationId, string $orderId): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = :org AND order_id = :order', ['org' => $organizationId, 'order' => $orderId]);

    return (int) $count;
  }

  /**
   * @return array<string,mixed>|null
   */
  private function one(string $table, string $organizationId, string $id): ?array
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM ' . $table . ' WHERE organization_id = :org AND id = :id', ['org' => $organizationId, 'id' => $id]);

    return false === $row ? null : $row;
  }

  /**
   * @param array<string,mixed> $values
   */
  private function upsert(string $table, array $values): void
  {
    $names = array_keys($values);
    $updates = [];
    foreach ($names as $name) {
      if (!in_array($name, ['id', 'organization_id', 'created_at'], true) && ('procurement_receipts' !== $table || in_array($name, ['inventory_movement_id', 'equipment_ids', 'returned_quantity', 'pending_return_quantity', 'blocked_reason', 'revision'], true)) && ('procurement_returns' !== $table || in_array($name, ['status', 'inventory_movement_id', 'blocked_reason', 'reconciled_at', 'revision'], true))) {
        $updates[] = $name . ' = EXCLUDED.' . $name;
      }
    }
    $affected = $this->connection->executeStatement('INSERT INTO ' . $table . ' (' . implode(', ', $names) . ') VALUES (' . implode(', ', array_map(static fn (string $name): string => ':' . $name, $names)) . ') ON CONFLICT (id) DO UPDATE SET ' . implode(', ', $updates) . ' WHERE ' . $table . '.organization_id = EXCLUDED.organization_id', $values);
    if (1 !== $affected) {
      throw ProcurementException::notFound();
    }
  }

  /**
   * @param array<string,mixed> $parameters
   *
   * @return list<array<string,mixed>>
   */
  private function page(string $table, string $criteria, array $parameters, int $offset, int $limit, string $sort): array
  {
    return $this->connection->fetchAllAssociative('SELECT * FROM ' . $table . ' WHERE ' . $criteria . ' ORDER BY ' . $sort . ' LIMIT :limit OFFSET :offset', $parameters + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);
  }

  private function supplierCriteria(?bool $archived): string
  {
    return 'organization_id = :org' . (null === $archived ? '' : ' AND archived_at IS ' . ($archived ? 'NOT ' : '') . 'NULL') . " AND (name ILIKE :search OR COALESCE(code, '') ILIKE :search)";
  }

  /**
   * @return array{string,array<string,string>}
   */
  private function orderCriteria(string $organizationId, ?string $status, ?string $supplierId): array
  {
    $criteria = 'organization_id = :org';
    $parameters = ['org' => $organizationId];
    if (null !== $status) {
      $criteria .= ' AND status = :status';
      $parameters['status'] = $status;
    }
    if (null !== $supplierId) {
      $criteria .= ' AND supplier_id = :supplier';
      $parameters['supplier'] = $supplierId;
    }

    return [$criteria, $parameters];
  }

  /**
   * @param array<string,mixed> $row
   */
  private function supplierFromRow(array $row): Supplier
  {
    /** @var array{id:string,organization_id:string,name:string,code:?string,email:?string,phone:?string,contacts:string,archived_at:?string,created_at:string,updated_at:string,revision:int|string} $row */
    /** @var list<array{name:string,email:?string,phone:?string,role:?string}> $contacts */
    $contacts = json_decode($row['contacts'], true, 512, JSON_THROW_ON_ERROR);

    return Supplier::reconstitute($row['id'], $row['organization_id'], $row['name'], $row['code'], $row['email'], $row['phone'], $contacts, $this->dateOrNull($row['archived_at']), $this->date($row['created_at']), $this->date($row['updated_at']), (int) $row['revision']);
  }

  /**
   * @param array<string,mixed> $row
   */
  private function orderFromRow(array $row): PurchaseOrder
  {
    /** @var array{id:string,organization_id:string,supplier_id:string,currency:string,name:string,lines:string,status:string,revision:int|string,created_at:string,updated_at:string} $row */
    /** @var list<array{id:string,kind:string,partId:?string,typeCode:?string,identityTemplate:array<string,mixed>,quantity:string,unitCost:?string,receivedQuantity:string,returnedQuantity:string,partCode?:?string,partLabel?:?string,partUnit?:?string}> $stored */
    $stored = json_decode($row['lines'], true, 512, JSON_THROW_ON_ERROR);
    $lines = [];
    foreach ($stored as $line) {
      $lines[] = ProcurementLine::reconstitute($line['id'], $line['kind'], $line['partId'], $line['typeCode'], $line['identityTemplate'], $line['quantity'], $line['unitCost'], $line['receivedQuantity'], $line['returnedQuantity'], $line['partCode'] ?? null, $line['partLabel'] ?? null, $line['partUnit'] ?? null);
    }

    return PurchaseOrder::reconstitute($row['id'], $row['organization_id'], $row['supplier_id'], $row['currency'], $row['name'], $lines, PurchaseOrderStatus::from($row['status']), (int) $row['revision'], $this->date($row['created_at']), $this->date($row['updated_at']));
  }

  /**
   * @param array<string,mixed> $row
   */
  private function receiptFromRow(array $row): ProcurementReceiptState
  {
    /** @var array{id:string,organization_id:string,order_id:string,line_id:string,kind:string,quantity:string,warehouse_id:?string,unit_cost:?string,currency:string,received_at:string,actor_id:string,created_at:string,inventory_movement_id:?string,equipment_ids:string,returned_quantity:string,pending_return_quantity:string,blocked_reason:?string,revision:int|string} $row */
    /** @var list<string> $equipmentIds */
    $equipmentIds = json_decode($row['equipment_ids'], true, 512, JSON_THROW_ON_ERROR);

    return new ProcurementReceiptState($row['id'], $row['organization_id'], $row['order_id'], $row['line_id'], $row['kind'], $row['quantity'], $row['warehouse_id'], $row['unit_cost'], $row['currency'], $this->date($row['received_at']), $row['actor_id'], $this->date($row['created_at']), $row['inventory_movement_id'], $equipmentIds, $row['returned_quantity'], $row['blocked_reason'], (int) $row['revision'], $row['pending_return_quantity']);
  }

  /**
   * @param array<string,mixed> $row
   */
  private function returnFromRow(array $row): ProcurementReturnState
  {
    /** @var array{id:string,organization_id:string,receipt_id:string,client_operation_id:string,quantity:string,reason:string,actor_id:string,created_at:string,status:string,inventory_movement_id:?string,blocked_reason:?string,reconciled_at:?string,revision:int|string} $row */

    return new ProcurementReturnState($row['id'], $row['organization_id'], $row['receipt_id'], $row['client_operation_id'], $row['quantity'], $row['reason'], $row['actor_id'], $this->date($row['created_at']), $row['status'], $row['inventory_movement_id'], $row['blocked_reason'], $this->dateOrNull($row['reconciled_at']), (int) $row['revision']);
  }

  private function time(?DateTimeImmutable $time): ?string
  {
    return $time?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
  }

  private function date(string $value): DateTimeImmutable
  {
    return new DateTimeImmutable($value, new DateTimeZone('UTC'));
  }

  private function dateOrNull(?string $value): ?DateTimeImmutable
  {
    return null === $value ? null : $this->date($value);
  }

  /**
   * @param array<array-key,mixed> $value
   */
  private function json(array $value): string
  {
    return json_encode($value, JSON_THROW_ON_ERROR);
  }
}
