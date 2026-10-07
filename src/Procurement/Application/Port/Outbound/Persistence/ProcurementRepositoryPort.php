<?php

declare(strict_types=1);

namespace Procurement\Application\Port\Outbound\Persistence;

use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Domain\Model\{PurchaseOrder, Supplier};

/** Persistence and ambient main transaction boundary for all Procurement mutations. */
interface ProcurementRepositoryPort
{
  /**
   * @template T
   *
   * @param callable():T $work
   *
   * @return T
   */
  public function synchronized(string $organizationId, callable $work): mixed;

  public function supplier(string $organizationId, string $id): ?Supplier;

  public function saveSupplier(Supplier $supplier): void;

  public function order(string $organizationId, string $id): ?PurchaseOrder;

  public function saveOrder(PurchaseOrder $order): void;

  public function receipt(string $organizationId, string $id): ?ProcurementReceiptState;

  public function saveReceipt(ProcurementReceiptState $receipt): void;

  public function returnDeclaration(string $organizationId, string $id): ?ProcurementReturnState;

  public function saveReturn(ProcurementReturnState $return): void;

  /**
   * @return list<ProcurementReturnState>
   */
  public function returns(string $organizationId, string $receiptId, int $offset, int $limit): array;

  public function countReturns(string $organizationId, string $receiptId): int;

  public function operation(string $organizationId, string $clientOperationId): ?ProcurementOperationState;

  public function saveOperation(ProcurementOperationState $operation): void;

  /**
   * @return list<Supplier>
   */
  public function suppliers(string $organizationId, string $search, ?bool $archived, int $offset, int $limit): array;

  public function countSuppliers(string $organizationId, string $search, ?bool $archived): int;

  /**
   * @return list<PurchaseOrder>
   */
  public function orders(string $organizationId, ?string $status, ?string $supplierId, int $offset, int $limit): array;

  public function countOrders(string $organizationId, ?string $status, ?string $supplierId): int;

  /**
   * @return list<ProcurementReceiptState>
   */
  public function receipts(string $organizationId, string $orderId, int $offset, int $limit): array;

  public function countReceipts(string $organizationId, string $orderId): int;
}
