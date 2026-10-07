<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\{ArrayParameterType,Connection,Exception\UniqueConstraintViolationException,ParameterType};
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Application\Port\Outbound\InventoryStorePort;
use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use LogicException;
use Shared\Domain\ValueObject\DecimalAmount;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function array_keys;
use function array_map;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function json_encode;
use function str_replace;

use const JSON_THROW_ON_ERROR;

/** SQL reads and locks only Inventory tables in main. @category Repository */
final readonly class InventoryRepository implements InventoryStorePort
{
  private DateTimeZone $storageZone;

  public function __construct(private EntityManagerInterface $entityManager, #[Autowire('%env(default:database_storage_timezone_default:DATABASE_STORAGE_TIMEZONE)%')] string $storageTimeZone = 'UTC')
  {
    $this->storageZone = new DateTimeZone($storageTimeZone);
  }

  public function operationForUpdate(string $org, string $operationId): ?InventoryOperationReceipt
  {
    $this->lock('inventory-operation:' . $org . ':' . $operationId);
    $row = $this->connection()->fetchAssociative('SELECT * FROM inventory_operation_receipts WHERE organization_id=:org AND client_operation_id=:op', ['org' => $org, 'op' => $operationId]);
    if (false === $row) {
      return null;
    }
    $response = json_decode(self::s($row, 'response'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($response)) {
      throw new LogicException('Invalid stored operation response.');
    }

    /** @var array<string,mixed> $response */
    return new InventoryOperationReceipt($org, $operationId, self::s($row, 'payload_hash'), $response);
  }

  public function saveOperation(InventoryOperationReceipt $receipt): void
  {
    $this->connection()->insert('inventory_operation_receipts', ['organization_id' => $receipt->organizationId, 'client_operation_id' => $receipt->clientOperationId, 'payload_hash' => $receipt->payloadHash, 'response' => json_encode($receipt->response, JSON_THROW_ON_ERROR)]);
  }

  public function lockIntervention(string $org, string $interventionId): void
  {
    $this->lock('inventory-intervention:' . $org . ':' . $interventionId);
  }

  public function reference(string $type, string $org, string $id, bool $lock = false): ?InventoryReference
  {
    $table = $this->table($type);
    $row = $this->connection()->fetchAssociative('SELECT * FROM ' . $table . ' WHERE organization_id=:org AND id=:id' . ($lock ? ' FOR UPDATE' : ''), ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->referenceRow($type, $row);
  }

  public function saveReference(string $type, InventoryReference $reference): void
  {
    $data = ['organization_id' => $reference->organizationId, 'code' => $reference->code, 'label' => $reference->label, 'archived' => $reference->archived];
    if ('parts' === $type) {
      $data['unit'] = $reference->unit;
      $data['kind'] = $reference->kind;
    }

    try {
      $this->connection()->executeStatement('INSERT INTO ' . $this->table($type) . ' (id,' . implode(',', array_keys($data)) . ') VALUES (:id,' . implode(',', array_map(static fn (string $k): string => ':' . $k, array_keys($data))) . ') ON CONFLICT (id) DO UPDATE SET label=EXCLUDED.label,archived=EXCLUDED.archived' . ('parts' === $type ? ',unit=EXCLUDED.unit' : ''), ['id' => $reference->id] + $data, ['archived' => ParameterType::BOOLEAN]);
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
    $rows = $this->connection()->fetchAllAssociative('SELECT * FROM ' . $this->table($type) . ' WHERE organization_id=:org AND id IN (:ids)', ['org' => $org, 'ids' => array_values(array_unique($ids))], ['ids' => ArrayParameterType::STRING]);
    $references = [];
    foreach ($rows as $row) {
      $item = $this->referenceRow($type, $row);
      $references[$item->id] = $item;
    }

    return $references;
  }

  public function balanceForUpdate(string $org, string $warehouseId, string $partId): ?StockBalance
  {
    $this->lock('inventory-balance:' . $org . ':' . $warehouseId . ':' . $partId);
    $row = $this->connection()->fetchAssociative('SELECT * FROM inventory_balances WHERE organization_id=:org AND warehouse_id=:warehouse AND part_id=:part FOR UPDATE', ['org' => $org, 'warehouse' => $warehouseId, 'part' => $partId]);

    return false === $row ? null : $this->balanceRow($row);
  }

  public function saveBalance(StockBalance $balance): void
  {
    $this->connection()->executeStatement('INSERT INTO inventory_balances(id,organization_id,warehouse_id,part_id,quantity,total_value,currency) VALUES (:id,:org,:warehouse,:part,:quantity,:value,:currency) ON CONFLICT (organization_id,warehouse_id,part_id) DO UPDATE SET quantity=EXCLUDED.quantity,total_value=EXCLUDED.total_value', ['id' => $balance->id, 'org' => $balance->organizationId, 'warehouse' => $balance->warehouseId, 'part' => $balance->partId, 'quantity' => $balance->quantity, 'value' => $balance->totalValue, 'currency' => $balance->currency]);
  }

  public function saveMovement(StockMovement $movement): void
  {
    $this->connection()->insert('inventory_movements', ['id' => $movement->id, 'organization_id' => $movement->organizationId, 'warehouse_id' => $movement->warehouseId, 'part_id' => $movement->partId, 'kind' => $movement->kind, 'quantity' => $movement->quantity, 'unit_cost' => $movement->unitCost, 'total_value' => $movement->totalValue, 'currency' => $movement->currency, 'reason' => $movement->reason, 'actor_id' => $movement->actorId, 'occurred_at' => $movement->occurredAt->setTimezone($this->storageZone)->format('Y-m-d H:i:s'), 'intervention_id' => $movement->interventionId, 'work_item_id' => $movement->workItemId, 'equipment_id' => $movement->equipmentId, 'correction_of' => $movement->correctionOf, 'source_receipt_id' => $movement->sourceReceiptId, 'late' => $movement->late], ['late' => ParameterType::BOOLEAN]);
  }

  public function movement(string $org, string $id): ?StockMovement
  {
    $row = $this->connection()->fetchAssociative('SELECT * FROM inventory_movements WHERE organization_id=:org AND id=:id', ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->movementRow($row);
  }

  public function linkedQuantity(string $org, string $movementId): string
  {
    $value = $this->connection()->fetchOne('SELECT COALESCE(SUM(ABS(quantity)),0) FROM inventory_movements WHERE organization_id=:org AND correction_of=:id', ['org' => $org, 'id' => $movementId]);
    if (!is_string($value) && !is_int($value)) {
      throw new LogicException('Invalid linked quantity.');
    }

    return DecimalAmount::fromString((string) $value)->toString();
  }

  public function linkedValue(string $org, string $movementId): ?string
  {
    $row = $this->connection()->fetchAssociative('SELECT COALESCE(SUM(ABS(total_value)),0) AS amount,COUNT(*) FILTER (WHERE total_value IS NULL) AS unknown FROM inventory_movements WHERE organization_id=:org AND correction_of=:id', ['org' => $org, 'id' => $movementId]);
    if (false === $row) {
      throw new LogicException('Invalid linked movement aggregate.');
    }

    $unknown = $row['unknown'] ?? null;
    if (!is_int($unknown) && !is_string($unknown)) {
      throw new LogicException('Invalid linked valuation count.');
    }

    return 0 !== (int) $unknown ? null : DecimalAmount::fromString(self::s($row, 'amount'))->toString();
  }

  public function saveDeclaration(ConsumptionDeclaration $declaration): void
  {
    $this->connection()->executeStatement('INSERT INTO inventory_declarations(id,organization_id,warehouse_id,part_id,quantity,intervention_id,work_item_id,equipment_id,actor_id,occurred_at,status,reason,movement_id,late) VALUES (:id,:org,:warehouse,:part,:quantity,:intervention,:work,:equipment,:actor,:occurred,:status,:reason,:movement,:late) ON CONFLICT (id) DO UPDATE SET status=EXCLUDED.status,reason=EXCLUDED.reason,movement_id=EXCLUDED.movement_id,late=EXCLUDED.late', ['id' => $declaration->id, 'org' => $declaration->organizationId, 'warehouse' => $declaration->warehouseId, 'part' => $declaration->partId, 'quantity' => $declaration->quantity, 'intervention' => $declaration->interventionId, 'work' => $declaration->workItemId, 'equipment' => $declaration->equipmentId, 'actor' => $declaration->actorId, 'occurred' => $declaration->occurredAt->setTimezone($this->storageZone)->format('Y-m-d H:i:s'), 'status' => $declaration->status, 'reason' => $declaration->reason, 'movement' => $declaration->movementId, 'late' => $declaration->late], ['late' => ParameterType::BOOLEAN]);
  }

  public function declaration(string $org, string $id, bool $lock = false): ?ConsumptionDeclaration
  {
    $row = $this->connection()->fetchAssociative('SELECT * FROM inventory_declarations WHERE organization_id=:org AND id=:id' . ($lock ? ' FOR UPDATE' : ''), ['org' => $org, 'id' => $id]);

    return false === $row ? null : $this->declarationRow($row);
  }

  public function hasPendingDeclarations(string $org, string $interventionId): bool
  {
    $count = $this->connection()->fetchOne("SELECT COUNT(*) FROM inventory_declarations WHERE organization_id=:org AND intervention_id=:intervention AND status='received_pending'", ['org' => $org, 'intervention' => $interventionId]);
    if (!is_string($count) && !is_int($count)) {
      throw new LogicException('Invalid declaration count.');
    }

    return (int) $count > 0;
  }

  public function interventionMovements(string $org, string $interventionId): array
  {
    $rows = $this->connection()->fetchAllAssociative('SELECT * FROM inventory_movements WHERE organization_id=:org AND intervention_id=:intervention ORDER BY occurred_at,id LIMIT 10001', ['org' => $org, 'intervention' => $interventionId]);
    if (count($rows) > 10000) {
      throw new InventoryConflictException('Intervention inventory facts exceed the bounded publication limit.');
    }

    return array_map($this->movementRow(...), $rows);
  }

  public function list(string $type, string $org, array $filters, int $limit, int $offset): array
  {
    [$where,$params] = $this->selection($type, $org, $filters);
    $rows = $this->connection()->fetchAllAssociative('SELECT * FROM ' . $this->table($type) . ' WHERE ' . $where . ' ORDER BY id ASC LIMIT :limit OFFSET :offset', $params + ['limit' => $limit, 'offset' => $offset], ['limit' => ParameterType::INTEGER, 'offset' => ParameterType::INTEGER]);

    return array_map(fn (array $r): InventoryReference|StockBalance|StockMovement|ConsumptionDeclaration => match($type) {
      'parts','warehouses' => $this->referenceRow($type, $r),'balances' => $this->balanceRow($r),'movements' => $this->movementRow($r),'consumptions' => $this->declarationRow($r),default => throw new LogicException('Unknown inventory projection.')
    }, $rows);
  }

  public function count(string $type, string $org, array $filters): int
  {
    [$where,$params] = $this->selection($type, $org, $filters);

    $count = $this->connection()->fetchOne('SELECT COUNT(*) FROM ' . $this->table($type) . ' WHERE ' . $where, $params);
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
    $ids = $this->connection()->fetchFirstColumn("SELECT intervention_id FROM inventory_movements WHERE organization_id = :org AND intervention_id IS NOT NULL AND equipment_id IN (:equipment) UNION SELECT intervention_id FROM inventory_declarations WHERE organization_id = :org AND status = 'received_pending' AND equipment_id IN (:equipment) ORDER BY intervention_id LIMIT 10001", ['org' => $org, 'equipment' => $equipmentIds], ['equipment' => ArrayParameterType::STRING]);
    if (count($ids) > 10000) {
      throw new InventoryConflictException('The financial material scope exceeds 10000 interventions; narrow its target filters.');
    }

    return $ids;
  }

  private function connection(): Connection
  {
    return $this->entityManager->getConnection();
  }

  private function lock(string $identity): void
  {
    if (!$this->connection()->isTransactionActive()) {
      throw new LogicException('Inventory write requires a main transaction.');
    }
    $this->connection()->executeQuery('SELECT pg_advisory_xact_lock(hashtextextended(:identity,0))', ['identity' => $identity]);
  }

  private function table(string $type): string
  {
    return match($type) {
      'parts' => 'inventory_parts','warehouses' => 'inventory_warehouses','balances' => 'inventory_balances','movements' => 'inventory_movements','consumptions' => 'inventory_declarations',default => throw new LogicException('Unknown inventory collection.')
    };
  }

  /**
   * @param array<string,string> $filters the exact collection filters
   *
   * @return array{string,array<string,string>} SQL predicate and bound parameters
   */
  private function selection(string $type, string $org, array $filters): array
  {
    $where = 'organization_id=:org';
    $params = ['org' => $org];
    if (in_array($type, ['parts', 'warehouses'], true)) {
      if (isset($filters['search'])) {
        $where .= " AND (code ILIKE :search ESCAPE '!' OR label ILIKE :search ESCAPE '!')";
        $params['search'] = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $filters['search']) . '%';
      }
      if (isset($filters['archived'])) {
        $where .= ' AND archived = CAST(:archived AS BOOLEAN)';
        $params['archived'] = $filters['archived'];
      }
    }
    foreach (['warehouseId' => 'warehouse_id', 'partId' => 'part_id', 'interventionId' => 'intervention_id', 'status' => 'status'] as $key => $column) {
      if (isset($filters[$key])) {
        $where .= ' AND ' . $column . '=:' . $key;
        $params[$key] = $filters[$key];
      }
    }

    return [$where, $params];
  }

  /**
   * @param array<string,mixed> $row
   */
  private function referenceRow(string $type, array $row): InventoryReference
  {
    return new InventoryReference(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'code'), self::s($row, 'label'), 'parts' === $type ? self::s($row, 'unit') : null, 'parts' === $type ? self::s($row, 'kind') : null, (bool) $row['archived']);
  }

  /**
   * @param array<string,mixed> $row
   */
  private function balanceRow(array $row): StockBalance
  {
    return new StockBalance(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'quantity'), self::n($row, 'total_value'), self::s($row, 'currency'));
  }

  /**
   * @param array<string,mixed> $row
   */
  private function movementRow(array $row): StockMovement
  {
    return new StockMovement(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'kind'), self::s($row, 'quantity'), self::n($row, 'unit_cost'), self::n($row, 'total_value'), self::s($row, 'currency'), self::s($row, 'reason'), self::s($row, 'actor_id'), new DateTimeImmutable(self::s($row, 'occurred_at'), $this->storageZone), self::n($row, 'intervention_id'), self::n($row, 'work_item_id'), self::n($row, 'equipment_id'), self::n($row, 'correction_of'), self::n($row, 'source_receipt_id'), (bool) $row['late']);
  }

  /**
   * @param array<string,mixed> $row
   */
  private function declarationRow(array $row): ConsumptionDeclaration
  {
    return new ConsumptionDeclaration(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'quantity'), self::s($row, 'intervention_id'), self::n($row, 'work_item_id'), self::n($row, 'equipment_id'), self::s($row, 'actor_id'), new DateTimeImmutable(self::s($row, 'occurred_at'), $this->storageZone), self::s($row, 'status'), self::n($row, 'reason'), self::n($row, 'movement_id'), (bool) $row['late']);
  }

  /**
   * @param array<string,mixed> $r
   */
  private static function s(array $r, string $key): string
  {
    if (!isset($r[$key]) || !is_string($r[$key])) {
      throw new LogicException('Malformed inventory persisted field ' . $key);
    }

    return $r[$key];
  }

  /**
   * @param array<string,mixed> $r
   */
  private static function n(array $r, string $key): ?string
  {
    return null === ($r[$key] ?? null) ? null : self::s($r, $key);
  }
}
