<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType,Connection,Exception\UniqueConstraintViolationException,ParameterType};
use InvalidArgumentException;
use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use Inventory\Infrastructure\Persistence\Doctrine\Lock\InventoryTransactionLock;
use Inventory\Infrastructure\Persistence\Doctrine\Mapper\InventoryRowMapper;
use Inventory\Infrastructure\Persistence\Doctrine\Query\InventoryCollectionQuery;
use LogicException;
use Shared\Domain\ValueObject\DecimalAmount;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function is_int;
use function is_string;
use function json_encode;

use const JSON_THROW_ON_ERROR;

/** SQL reads and locks only Inventory tables in main. @category Repository */
final readonly class InventoryRepository implements InventoryStorePort
{
  /**
   * Constant SELECT_ALL
   */
  private const string SELECT_ALL = 'SELECT * FROM ';

  /**
   * Property storageZone
   */
  private DateTimeZone $storageZone;

  /**
   * Property mapper
   */
  private InventoryRowMapper $mapper;

  /**
   * Property locks
   */
  private InventoryTransactionLock $locks;

  /**
   * Method __construct
   *
   * Shares the explicitly wired main connection with every query and transaction-scoped fence.
   *
   * @access public
   *
   * @param Connection $connection the owning main connection
   * @param string $storageTimeZone the storage zone for persisted wall-clock timestamps
   *
   * @return void
   */
  public function __construct(private Connection $connection, #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')] string $storageTimeZone = 'UTC')
  {
    $this->storageZone = new DateTimeZone($storageTimeZone);
    $this->mapper = new InventoryRowMapper($this->storageZone);
    $this->locks = new InventoryTransactionLock($this->connection);
  }

  public function operationForUpdate(string $org, string $operationId): ?InventoryOperationReceipt
  {
    $this->locks->acquire('inventory-operation:' . $org . ':' . $operationId);
    $row = $this->connection->fetchAssociative('SELECT * FROM inventory_operation_receipts WHERE organization_id=:org AND client_operation_id=:op', ['org' => $org, 'op' => $operationId]);
    if (false === $row) {
      return null;
    }

    return $this->mapper->operationReceipt($org, $operationId, $row);
  }

  public function saveOperation(InventoryOperationReceipt $receipt): void
  {
    $this->connection->insert('inventory_operation_receipts', ['organization_id' => $receipt->organizationId, 'client_operation_id' => $receipt->clientOperationId, 'payload_hash' => $receipt->payloadHash, 'response' => json_encode($receipt->response, JSON_THROW_ON_ERROR)]);
  }

  public function lockIntervention(string $org, string $interventionId): void
  {
    $this->locks->acquire('inventory-intervention:' . $org . ':' . $interventionId);
  }

  public function reference(string $type, string $org, string $id, bool $lock = false): ?InventoryReference
  {
    $table = InventoryCollectionQuery::table($type);
    $row = $this->connection->fetchAssociative(self::SELECT_ALL . $table . ' WHERE organization_id=:org AND id=:id' . ($lock ? ' FOR UPDATE' : ''), ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->mapper->reference($type, $row);
  }

  public function saveReference(string $type, InventoryReference $reference): void
  {
    $data = ['organization_id' => $reference->organizationId, 'code' => $reference->code, 'label' => $reference->label, 'archived' => $reference->archived];
    if ('parts' === $type) {
      $data['unit'] = $reference->unit;
      $data['kind'] = $reference->kind;
    }

    try {
      $this->connection->executeStatement('INSERT INTO ' . InventoryCollectionQuery::table($type) . ' (id,' . implode(',', array_keys($data)) . ') VALUES (:id,' . implode(',', array_map(static fn (string $k): string => ':' . $k, array_keys($data))) . ') ON CONFLICT (id) DO UPDATE SET label=EXCLUDED.label,archived=EXCLUDED.archived' . ('parts' === $type ? ',unit=EXCLUDED.unit' : ''), ['id' => $reference->id] + $data, ['archived' => ParameterType::BOOLEAN]);
    } catch (UniqueConstraintViolationException $e) {
      throw new InventoryConflictException('Inventory reference code already exists.', 0, $e);
    }
  }

  public function referencesByIds(string $type, string $org, array $ids): array
  {
    if ([] === $ids) {
      return [];
    }
    if (count($ids) > 100) {
      throw new InvalidArgumentException('Reference batches contain at most one hundred identities.');
    }
    $rows = $this->connection->fetchAllAssociative(self::SELECT_ALL . InventoryCollectionQuery::table($type) . ' WHERE organization_id=:org AND id IN (:ids)', ['org' => $org, 'ids' => array_values(array_unique($ids))], ['ids' => ArrayParameterType::STRING]);
    $references = [];
    foreach ($rows as $row) {
      $item = $this->mapper->reference($type, $row);
      $references[$item->id] = $item;
    }

    return $references;
  }

  public function balanceForUpdate(string $org, string $warehouseId, string $partId): ?StockBalance
  {
    $this->locks->acquire('inventory-balance:' . $org . ':' . $warehouseId . ':' . $partId);
    $row = $this->connection->fetchAssociative('SELECT * FROM inventory_balances WHERE organization_id=:org AND warehouse_id=:warehouse AND part_id=:part FOR UPDATE', ['org' => $org, 'warehouse' => $warehouseId, 'part' => $partId]);

    return false === $row ? null : $this->mapper->balance($row);
  }

  public function saveBalance(StockBalance $balance): void
  {
    $this->connection->executeStatement('INSERT INTO inventory_balances(id,organization_id,warehouse_id,part_id,quantity,total_value,currency) VALUES (:id,:org,:warehouse,:part,:quantity,:value,:currency) ON CONFLICT (organization_id,warehouse_id,part_id) DO UPDATE SET quantity=EXCLUDED.quantity,total_value=EXCLUDED.total_value', ['id' => $balance->id, 'org' => $balance->organizationId, 'warehouse' => $balance->warehouseId, 'part' => $balance->partId, 'quantity' => $balance->quantity, 'value' => $balance->totalValue, 'currency' => $balance->currency]);
  }

  public function saveMovement(StockMovement $movement): void
  {
    $this->connection->insert('inventory_movements', ['id' => $movement->id, 'organization_id' => $movement->organizationId, 'warehouse_id' => $movement->warehouseId, 'part_id' => $movement->partId, 'kind' => $movement->kind, 'quantity' => $movement->quantity, 'unit_cost' => $movement->unitCost, 'total_value' => $movement->totalValue, 'currency' => $movement->currency, 'reason' => $movement->reason, 'actor_id' => $movement->actorId, 'occurred_at' => $movement->occurredAt->setTimezone($this->storageZone)->format('Y-m-d H:i:s'), 'intervention_id' => $movement->interventionId, 'work_item_id' => $movement->workItemId, 'equipment_id' => $movement->equipmentId, 'correction_of' => $movement->correctionOf, 'source_receipt_id' => $movement->sourceReceiptId, 'late' => $movement->late], ['late' => ParameterType::BOOLEAN]);
  }

  public function movement(string $org, string $id): ?StockMovement
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM inventory_movements WHERE organization_id=:org AND id=:id', ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->mapper->movement($row);
  }

  public function linkedQuantity(string $org, string $movementId): string
  {
    $value = $this->connection->fetchOne('SELECT COALESCE(SUM(ABS(quantity)),0) FROM inventory_movements WHERE organization_id=:org AND correction_of=:id', ['org' => $org, 'id' => $movementId]);
    if (!is_string($value) && !is_int($value)) {
      throw new LogicException('Invalid linked quantity.');
    }

    return DecimalAmount::fromString((string) $value)->toString();
  }

  public function linkedValue(string $org, string $movementId): ?string
  {
    $row = $this->connection->fetchAssociative('SELECT COALESCE(SUM(ABS(total_value)),0) AS amount,COUNT(*) FILTER (WHERE total_value IS NULL) AS unknown FROM inventory_movements WHERE organization_id=:org AND correction_of=:id', ['org' => $org, 'id' => $movementId]);
    if (false === $row) {
      throw new LogicException('Invalid linked movement aggregate.');
    }

    return $this->mapper->linkedValue($row);
  }

  public function saveDeclaration(ConsumptionDeclaration $declaration): void
  {
    $this->connection->executeStatement('INSERT INTO inventory_declarations(id,organization_id,warehouse_id,part_id,quantity,intervention_id,work_item_id,equipment_id,actor_id,occurred_at,status,reason,movement_id,late) VALUES (:id,:org,:warehouse,:part,:quantity,:intervention,:work,:equipment,:actor,:occurred,:status,:reason,:movement,:late) ON CONFLICT (id) DO UPDATE SET status=EXCLUDED.status,reason=EXCLUDED.reason,movement_id=EXCLUDED.movement_id,late=EXCLUDED.late', ['id' => $declaration->id, 'org' => $declaration->organizationId, 'warehouse' => $declaration->warehouseId, 'part' => $declaration->partId, 'quantity' => $declaration->quantity, 'intervention' => $declaration->interventionId, 'work' => $declaration->workItemId, 'equipment' => $declaration->equipmentId, 'actor' => $declaration->actorId, 'occurred' => $declaration->occurredAt->setTimezone($this->storageZone)->format('Y-m-d H:i:s'), 'status' => $declaration->status, 'reason' => $declaration->reason, 'movement' => $declaration->movementId, 'late' => $declaration->late], ['late' => ParameterType::BOOLEAN]);
  }

  public function declaration(string $org, string $id, bool $lock = false): ?ConsumptionDeclaration
  {
    $row = $this->connection->fetchAssociative('SELECT * FROM inventory_declarations WHERE organization_id=:org AND id=:id' . ($lock ? ' FOR UPDATE' : ''), ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->mapper->declaration($row);
  }

  public function hasPendingDeclarations(string $org, string $interventionId): bool
  {
    $count = $this->connection->fetchOne("SELECT COUNT(*) FROM inventory_declarations WHERE organization_id=:org AND intervention_id=:intervention AND status='received_pending'", ['org' => $org, 'intervention' => $interventionId]);
    if (!is_string($count) && !is_int($count)) {
      throw new LogicException('Invalid declaration count.');
    }

    return (int) $count > 0;
  }

  public function interventionMovements(string $org, string $interventionId): array
  {
    $rows = $this->connection->fetchAllAssociative('SELECT * FROM inventory_movements WHERE organization_id=:org AND intervention_id=:intervention ORDER BY occurred_at,id LIMIT 10001', ['org' => $org, 'intervention' => $interventionId]);
    if (count($rows) > 10000) {
      throw new InventoryConflictException('Intervention inventory facts exceed the bounded publication limit.');
    }

    return $this->mapper->movements($rows);
  }

  public function list(string $type, string $org, array $filters, int $limit, int $offset): array
  {
    [$where,$params] = InventoryCollectionQuery::selection($type, $org, $filters);
    $rows = $this->connection->fetchAllAssociative(self::SELECT_ALL . InventoryCollectionQuery::table($type) . ' WHERE ' . $where . ' ORDER BY id ASC LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

    return $this->mapper->collection($type, $rows);
  }

  public function count(string $type, string $org, array $filters): int
  {
    [$where,$params] = InventoryCollectionQuery::selection($type, $org, $filters);

    $count = $this->connection->fetchOne('SELECT COUNT(*) FROM ' . InventoryCollectionQuery::table($type) . ' WHERE ' . $where, $params);
    if (!is_string($count) && !is_int($count)) {
      throw new LogicException('Invalid inventory count.');
    }

    return (int) $count;
  }

  /**
   * Method economicInterventionIds
   *
   * Counts no sibling records and returns no valuations or quantities.
   *
   * @access public
   *
   * @param string $org authorized organization
   * @param list<string> $equipmentIds bounded equipment scope
   *
   * @return list<string> distinct candidate intervention identifiers
   */
  public function economicInterventionIds(string $org, array $equipmentIds): array
  {
    $equipmentIds = array_values(array_unique($equipmentIds));
    if (count($equipmentIds) > 10000) {
      throw new InventoryConflictException('The financial equipment scope exceeds 10000 identities; narrow its target filters.');
    }
    if ([] === $equipmentIds) {
      return [];
    }
    /** @var list<string> $ids */
    $ids = $this->connection->fetchFirstColumn("SELECT intervention_id FROM inventory_movements WHERE organization_id = :org AND intervention_id IS NOT NULL AND equipment_id IN (:equipment) UNION SELECT intervention_id FROM inventory_declarations WHERE organization_id = :org AND status = 'received_pending' AND equipment_id IN (:equipment) ORDER BY intervention_id LIMIT 10001", ['org' => $org, 'equipment' => $equipmentIds], ['equipment' => ArrayParameterType::STRING]);
    if (count($ids) > 10000) {
      throw new InventoryConflictException('The financial material scope exceeds 10000 interventions; narrow its target filters.');
    }

    return $ids;
  }
}
