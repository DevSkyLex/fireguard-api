<?php

declare(strict_types=1);

namespace Inventory\Application\Port\Outbound;

use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};

/** @category Port */
interface InventoryStorePort
{
  public function operationForUpdate(string $org, string $operationId): ?InventoryOperationReceipt;

  public function saveOperation(InventoryOperationReceipt $receipt): void;

  public function lockIntervention(string $org, string $interventionId): void;

  public function reference(string $type, string $org, string $id, bool $lock = false): ?InventoryReference;

  public function saveReference(string $type, InventoryReference $reference): void;

  /**
   * @param list<string> $ids bounded scoped references
   *
   * @return array<string,InventoryReference> matching owned references, including archives
   */
  public function referencesByIds(string $type, string $org, array $ids): array;

  public function balanceForUpdate(string $org, string $warehouseId, string $partId): ?StockBalance;

  public function saveBalance(StockBalance $balance): void;

  public function saveMovement(StockMovement $movement): void;

  public function movement(string $org, string $id): ?StockMovement;

  public function linkedQuantity(string $org, string $movementId): string;

  public function linkedValue(string $org, string $movementId): ?string;

  public function saveDeclaration(ConsumptionDeclaration $declaration): void;

  public function declaration(string $org, string $id, bool $lock = false): ?ConsumptionDeclaration;

  public function hasPendingDeclarations(string $org, string $interventionId): bool;

  /**
   * @return list<StockMovement>
   */
  public function interventionMovements(string $org, string $interventionId): array;

  /**
   * Method economicInterventionIds
   *
   * Reads only owned direct-material references, never operational or equipment persistence.
   *
   * @access public
   *
   * @param string $org authorized owning organization
   * @param list<string> $equipmentIds at most 10000 equipment identifiers
   *
   * @return list<string> distinct intervention identifiers, refusing more than 10000 matches
   */
  public function economicInterventionIds(string $org, array $equipmentIds): array;

  /**
   * @param array<string,string> $filters the exact collection filters
   *
   * @return list<InventoryReference|StockBalance|StockMovement|ConsumptionDeclaration> projected rows
   */
  public function list(string $type, string $org, array $filters, int $limit, int $offset): array;

  /**
   * @param array<string,string> $filters
   */
  public function count(string $type, string $org, array $filters): int;
}
