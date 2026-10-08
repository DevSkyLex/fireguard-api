<?php

declare(strict_types=1);

namespace Procurement\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\DBAL\Connection;
use Procurement\Application\Contract\{ProcurementOperationState, ProcurementReceiptState, ProcurementReturnState};
use Procurement\Application\Port\Outbound\Persistence\ProcurementRepositoryPort;
use Procurement\Domain\Exception\ProcurementException;
use Procurement\Domain\Model\{PurchaseOrder, Supplier};
use Procurement\Infrastructure\Adapter\Doctrine\ProcurementTableStorageAdapter;
use Procurement\Infrastructure\Persistence\Doctrine\Mapper\ProcurementStateMapper;

use function array_map;

/** Explicit-main PostgreSQL repository with scoped reads and serialized physical operations. */
final readonly class ProcurementRepository implements ProcurementRepositoryPort
{
  /**
   * Property mapper
   */
  private ProcurementStateMapper $mapper;

  /**
   * Property storage
   */
  private ProcurementTableStorageAdapter $storage;

  /**
   * Method __construct
   *
   * @access public
   *
   * @param Connection $connection the connection value
   *
   * @return void
   */
  public function __construct(private Connection $connection)
  {
    $this->mapper = new ProcurementStateMapper();
    $this->storage = new ProcurementTableStorageAdapter($connection);
  }

  /**
   * Method synchronized
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param callable $work the work value
   *
   * @return mixed
   */
  public function synchronized(string $organizationId, callable $work): mixed
  {
    return $this->connection->transactional(function () use ($organizationId, $work): mixed {
      $this->connection->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:key, 0))', ['key' => 'procurement.' . $organizationId]);

      return $work();
    });
  }

  /**
   * Method supplier
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $id the id value
   *
   * @return ?Supplier
   */
  public function supplier(string $organizationId, string $id): ?Supplier
  {
    $row = $this->storage->one('procurement_suppliers', $organizationId, $id);

    return null === $row ? null : $this->mapper->supplierFromRow($row);
  }

  /**
   * Method saveSupplier
   *
   * @access public
   *
   * @param Supplier $supplier the supplier value
   *
   * @return void
   */
  public function saveSupplier(Supplier $supplier): void
  {
    $this->storage->upsert('procurement_suppliers', $this->mapper->supplierToRow($supplier));
  }

  /**
   * Method order
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $id the id value
   *
   * @return ?PurchaseOrder
   */
  public function order(string $organizationId, string $id): ?PurchaseOrder
  {
    $row = $this->storage->one('procurement_orders', $organizationId, $id);

    return null === $row ? null : $this->mapper->orderFromRow($row);
  }

  /**
   * Method saveOrder
   *
   * @access public
   *
   * @param PurchaseOrder $order the order value
   *
   * @return void
   */
  public function saveOrder(PurchaseOrder $order): void
  {
    $this->storage->upsert('procurement_orders', $this->mapper->orderToRow($order));
  }

  /**
   * Method receipt
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $id the id value
   *
   * @return ?ProcurementReceiptState
   */
  public function receipt(string $organizationId, string $id): ?ProcurementReceiptState
  {
    $row = $this->storage->one('procurement_receipts', $organizationId, $id);

    return null === $row ? null : $this->mapper->receiptFromRow($row);
  }

  /**
   * Method saveReceipt
   *
   * @access public
   *
   * @param ProcurementReceiptState $receipt the receipt value
   *
   * @return void
   */
  public function saveReceipt(ProcurementReceiptState $receipt): void
  {
    $this->storage->upsert('procurement_receipts', $this->mapper->receiptToRow($receipt));
  }

  /**
   * Method returnDeclaration
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $id the id value
   *
   * @return ?ProcurementReturnState
   */
  public function returnDeclaration(string $organizationId, string $id): ?ProcurementReturnState
  {
    $row = $this->storage->one('procurement_returns', $organizationId, $id);

    return null === $row ? null : $this->mapper->returnFromRow($row);
  }

  /**
   * Method saveReturn
   *
   * @access public
   *
   * @param ProcurementReturnState $return the return value
   *
   * @return void
   */
  public function saveReturn(ProcurementReturnState $return): void
  {
    $this->storage->upsert('procurement_returns', $this->mapper->returnToRow($return));
  }

  /**
   * Method returns
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $receiptId the receiptId value
   * @param int $offset the offset value
   * @param int $limit the limit value
   *
   * @return list<ProcurementReturnState>
   */
  public function returns(string $organizationId, string $receiptId, int $offset, int $limit): array
  {
    return array_map($this->mapper->returnFromRow(...), $this->storage->page('procurement_returns', 'organization_id = :org AND receipt_id = :receipt', ['org' => $organizationId, 'receipt' => $receiptId], $offset, $limit, 'created_at, id'));
  }

  /**
   * Method countReturns
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $receiptId the receiptId value
   *
   * @return int
   */
  public function countReturns(string $organizationId, string $receiptId): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_returns WHERE organization_id = :org AND receipt_id = :receipt', ['org' => $organizationId, 'receipt' => $receiptId]);

    return (int) $count;
  }

  /**
   * Method operation
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $clientOperationId the clientOperationId value
   *
   * @return ?ProcurementOperationState
   */
  public function operation(string $organizationId, string $clientOperationId): ?ProcurementOperationState
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM procurement_operations WHERE organization_id = :org AND client_operation_id = :operation', ['org' => $organizationId, 'operation' => $clientOperationId]);
    if (false === $row) {
      return null;
    }

    return $this->mapper->operationFromRow($row);
  }

  /**
   * Method saveOperation
   *
   * @access public
   *
   * @param ProcurementOperationState $operation the operation value
   *
   * @return void
   */
  public function saveOperation(ProcurementOperationState $operation): void
  {
    $existing = $this->operation($operation->organizationId, $operation->clientOperationId);
    if (null !== $existing) {
      if ($existing->kind !== $operation->kind || $existing->fingerprint !== $operation->fingerprint || $existing->receiptId !== $operation->receiptId) {
        throw ProcurementException::conflict('The retained operation key cannot be reused.');
      }

      return;
    }
    $this->connection->insert('procurement_operations', $this->mapper->operationToRow($operation));
  }

  /**
   * Method suppliers
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $search the search value
   * @param ?bool $archived the archived value
   * @param int $offset the offset value
   * @param int $limit the limit value
   *
   * @return list<Supplier>
   */
  public function suppliers(string $organizationId, string $search, ?bool $archived, int $offset, int $limit): array
  {
    $criteria = $this->storage->supplierCriteria($archived);
    $rows = $this->storage->page('procurement_suppliers', $criteria, ['org' => $organizationId, 'search' => '%' . $search . '%'], $offset, $limit, 'LOWER(name), id');

    return array_map($this->mapper->supplierFromRow(...), $rows);
  }

  /**
   * Method countSuppliers
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $search the search value
   * @param ?bool $archived the archived value
   *
   * @return int
   */
  public function countSuppliers(string $organizationId, string $search, ?bool $archived): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_suppliers WHERE ' . $this->storage->supplierCriteria($archived), ['org' => $organizationId, 'search' => '%' . $search . '%']);

    return (int) $count;
  }

  /**
   * Method orders
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param ?string $status the status value
   * @param ?string $supplierId the supplierId value
   * @param int $offset the offset value
   * @param int $limit the limit value
   *
   * @return list<PurchaseOrder>
   */
  public function orders(string $organizationId, ?string $status, ?string $supplierId, int $offset, int $limit): array
  {
    [$criteria, $parameters] = $this->storage->orderCriteria($organizationId, $status, $supplierId);

    return array_map($this->mapper->orderFromRow(...), $this->storage->page('procurement_orders', $criteria, $parameters, $offset, $limit, 'created_at DESC, id'));
  }

  /**
   * Method countOrders
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param ?string $status the status value
   * @param ?string $supplierId the supplierId value
   *
   * @return int
   */
  public function countOrders(string $organizationId, ?string $status, ?string $supplierId): int
  {
    [$criteria, $parameters] = $this->storage->orderCriteria($organizationId, $status, $supplierId);

    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_orders WHERE ' . $criteria, $parameters);

    return (int) $count;
  }

  /**
   * Method receipts
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $orderId the orderId value
   * @param int $offset the offset value
   * @param int $limit the limit value
   *
   * @return list<ProcurementReceiptState>
   */
  public function receipts(string $organizationId, string $orderId, int $offset, int $limit): array
  {
    return array_map($this->mapper->receiptFromRow(...), $this->storage->page('procurement_receipts', 'organization_id = :org AND order_id = :order', ['org' => $organizationId, 'order' => $orderId], $offset, $limit, 'created_at, id'));
  }

  /**
   * Method countReceipts
   *
   * @access public
   *
   * @param string $organizationId the organizationId value
   * @param string $orderId the orderId value
   *
   * @return int
   */
  public function countReceipts(string $organizationId, string $orderId): int
  {
    /** @var int|string $count */
    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM procurement_receipts WHERE organization_id = :org AND order_id = :order', ['org' => $organizationId, 'order' => $orderId]);

    return (int) $count;
  }
}
