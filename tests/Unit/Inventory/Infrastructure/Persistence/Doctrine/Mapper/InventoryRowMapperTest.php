<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeZone;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use Inventory\Infrastructure\Persistence\Doctrine\Mapper\InventoryRowMapper;
use JsonException;
use LogicException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Class InventoryRowMapperTest
 *
 * Protects exact persisted values, unknown valuations and timestamp restoration.
 *
 * @category Test
 */
final class InventoryRowMapperTest extends TestCase
{
  // #region Methods
  /**
   * Method referencesRetainCatalogFieldsAndArchiveFlags
   *
   * Preserves part metadata and the distinct warehouse projection.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function referencesRetainCatalogFieldsAndArchiveFlags(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $row = ['id' => 'part', 'organization_id' => 'org', 'code' => 'FILTER', 'label' => 'Replacement filter', 'unit' => 'piece', 'kind' => 'consumable', 'archived' => true];

    $part = $mapper->reference('parts', $row);

    self::assertSame(['part', 'org', 'FILTER', 'Replacement filter', 'piece', 'consumable', true], [$part->id, $part->organizationId, $part->code, $part->label, $part->unit, $part->kind, $part->archived]);

    unset($row['unit'], $row['kind']);
    $row['archived'] = false;
    $warehouse = $mapper->reference('warehouses', $row);

    self::assertNull($warehouse->unit);
    self::assertNull($warehouse->kind);
    self::assertFalse($warehouse->archived);
  }

  /**
   * Method balancesRetainExactDecimalsAndUnknownValuation
   *
   * Keeps nullable valuations distinct from exact monetary zero.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function balancesRetainExactDecimalsAndUnknownValuation(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $row = ['id' => 'balance', 'organization_id' => 'org', 'part_id' => 'part', 'warehouse_id' => 'warehouse', 'quantity' => '1.123456', 'total_value' => null, 'currency' => 'EUR'];

    $unknown = $mapper->balance($row);

    self::assertSame('1.123456', $unknown->quantity);
    self::assertNull($unknown->totalValue);
    self::assertSame(['balance', 'org', 'part', 'warehouse', 'EUR'], [$unknown->id, $unknown->organizationId, $unknown->partId, $unknown->warehouseId, $unknown->currency]);

    $row['total_value'] = '0.000001';
    self::assertSame('0.000001', $mapper->balance($row)->totalValue);
    $row['total_value'] = '0.000000';
    self::assertSame('0.000000', $mapper->balance($row)->totalValue);
  }

  /**
   * Method movementsRetainNullableLinksAndStoredTimeZone
   *
   * Interprets a stored wall-clock time without discarding decimal precision or unknown values.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function movementsRetainNullableLinksAndStoredTimeZone(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('Europe/Paris'));
    $row = self::movementRow('movement');
    $movement = $mapper->movement($row);

    self::assertSame(['movement', 'org', 'part', 'warehouse', 'consumption', '-0.123456', '0.000001', 'EUR', 'Original issue', 'actor'], [$movement->id, $movement->organizationId, $movement->partId, $movement->warehouseId, $movement->kind, $movement->quantity, $movement->totalValue, $movement->currency, $movement->reason, $movement->actorId]);
    self::assertNull($movement->unitCost);
    self::assertNull($movement->workItemId);
    self::assertNull($movement->equipmentId);
    self::assertNull($movement->correctionOf);
    self::assertNull($movement->sourceReceiptId);
    self::assertSame('intervention', $movement->interventionId);
    self::assertTrue($movement->late);
    self::assertSame('2026-10-08 12:34:56+02:00', $movement->occurredAt->format('Y-m-d H:i:sP'));
    self::assertSame('2026-10-08 10:34:56', $movement->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'));

    $row['total_value'] = null;
    $row['unit_cost'] = '1.123456';
    $row['work_item_id'] = 'work';
    $row['equipment_id'] = 'equipment';
    $row['correction_of'] = 'original';
    $row['source_receipt_id'] = 'receipt';
    $knownLinks = $mapper->movement($row);

    self::assertNull($knownLinks->totalValue);
    self::assertSame(['1.123456', 'work', 'equipment', 'original', 'receipt'], [$knownLinks->unitCost, $knownLinks->workItemId, $knownLinks->equipmentId, $knownLinks->correctionOf, $knownLinks->sourceReceiptId]);
  }

  /**
   * Method declarationsRetainContextAndResolutionFields
   *
   * Preserves pending and confirmed declaration restoration in the configured zone.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function declarationsRetainContextAndResolutionFields(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('Europe/Paris'));
    $row = self::declarationRow();
    $pending = $mapper->declaration($row);

    self::assertSame(['declaration', 'org', 'part', 'warehouse', '0.123456', 'intervention', 'actor', 'received_pending', 'insufficient_stock'], [$pending->id, $pending->organizationId, $pending->partId, $pending->warehouseId, $pending->quantity, $pending->interventionId, $pending->actorId, $pending->status, $pending->reason]);
    self::assertNull($pending->workItemId);
    self::assertNull($pending->equipmentId);
    self::assertNull($pending->movementId);
    self::assertFalse($pending->late);
    self::assertSame('2026-10-08 12:34:56+02:00', $pending->occurredAt->format('Y-m-d H:i:sP'));

    $row['work_item_id'] = 'work';
    $row['equipment_id'] = 'equipment';
    $row['status'] = 'confirmed';
    $row['reason'] = null;
    $row['movement_id'] = 'movement';
    $row['late'] = true;
    $confirmed = $mapper->declaration($row);

    self::assertSame(['work', 'equipment', 'confirmed', 'movement'], [$confirmed->workItemId, $confirmed->equipmentId, $confirmed->status, $confirmed->movementId]);
    self::assertNull($confirmed->reason);
    self::assertTrue($confirmed->late);
  }

  /**
   * Method operationReceiptsRestoreOriginalJsonSnapshot
   *
   * Keeps nested response values and the queried operation scope unchanged.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function operationReceiptsRestoreOriginalJsonSnapshot(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $receipt = $mapper->operationReceipt('org', 'operation', ['payload_hash' => 'hash', 'response' => '{"status":"received_pending","quantity":"0.123456","movementId":null,"late":true,"links":["work","equipment"]}']);

    self::assertSame(['org', 'operation', 'hash'], [$receipt->organizationId, $receipt->clientOperationId, $receipt->payloadHash]);
    self::assertSame(['status' => 'received_pending', 'quantity' => '0.123456', 'movementId' => null, 'late' => true, 'links' => ['work', 'equipment']], $receipt->response);
    self::assertSame([], $mapper->operationReceipt('org', 'operation', ['payload_hash' => 'hash', 'response' => '[]'])->response);
  }

  /**
   * Method malformedReceiptJsonIsRejected
   *
   * Retains JSON parsing failures for corrupt stored snapshots.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function malformedReceiptJsonIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $this->expectException(JsonException::class);

    $mapper->operationReceipt('org', 'operation', ['payload_hash' => 'hash', 'response' => '{']);
  }

  /**
   * Method scalarReceiptJsonIsRejected
   *
   * Requires an array response rather than coercing a scalar snapshot.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function scalarReceiptJsonIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Invalid stored operation response.');

    $mapper->operationReceipt('org', 'operation', ['payload_hash' => 'hash', 'response' => 'false']);
  }

  /**
   * Method linkedValuesCanonicalizePrecisionAndKeepUnknownAmounts
   *
   * Accepts PostgreSQL integer and string counts without fabricating a known valuation.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function linkedValuesCanonicalizePrecisionAndKeepUnknownAmounts(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));

    self::assertSame('0.000001', $mapper->linkedValue(['amount' => '0.000001', 'unknown' => 0]));
    self::assertSame('1.200000', $mapper->linkedValue(['amount' => '1.2', 'unknown' => '0']));
    self::assertSame('0.000000', $mapper->linkedValue(['amount' => '0', 'unknown' => 0]));
    self::assertNull($mapper->linkedValue(['amount' => '2.000000', 'unknown' => 1]));
    self::assertNull($mapper->linkedValue(['unknown' => '2']));
  }

  /**
   * Method malformedLinkedValueCountIsRejected
   *
   * Retains validation of the aggregate count before amount restoration.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function malformedLinkedValueCountIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Invalid linked valuation count.');

    $mapper->linkedValue(['amount' => '1.000000', 'unknown' => null]);
  }

  /**
   * Method malformedRequiredFieldIsRejected
   *
   * Refuses scalar coercion of required persisted identity fields.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function malformedRequiredFieldIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $row = self::movementRow('movement');
    $row['id'] = 42;
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Malformed inventory persisted field id');

    $mapper->movement($row);
  }

  /**
   * Method malformedNullableFieldIsRejected
   *
   * Keeps nullable values type-checked when they are present.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function malformedNullableFieldIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $row = self::movementRow('movement');
    $row['unit_cost'] = 42;
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Malformed inventory persisted field unit_cost');

    $mapper->movement($row);
  }

  /**
   * Method collectionsPreserveOrderAndSupportedProjections
   *
   * Retains input order rather than applying an additional sort during restoration.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function collectionsPreserveOrderAndSupportedProjections(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $rows = [self::movementRow('movement-z'), self::movementRow('movement-a')];
    $collection = $mapper->collection('movements', $rows);

    self::assertSame(['movement-z', 'movement-a'], [$collection[0]->id, $collection[1]->id]);
    self::assertInstanceOf(StockMovement::class, $collection[0]);
    $movements = $mapper->movements($rows);
    self::assertSame(['movement-z', 'movement-a'], [$movements[0]->id, $movements[1]->id]);
    self::assertSame([], $mapper->movements([]));
    self::assertSame([], $mapper->collection('unknown', []));

    $reference = ['id' => 'part', 'organization_id' => 'org', 'code' => 'PART', 'label' => 'Part', 'unit' => 'piece', 'kind' => 'part', 'archived' => false];
    self::assertInstanceOf(InventoryReference::class, $mapper->collection('parts', [$reference])[0]);
    self::assertInstanceOf(InventoryReference::class, $mapper->collection('warehouses', [$reference])[0]);
    self::assertInstanceOf(StockBalance::class, $mapper->collection('balances', [['id' => 'balance', 'organization_id' => 'org', 'part_id' => 'part', 'warehouse_id' => 'warehouse', 'quantity' => '1.000000', 'currency' => 'EUR']])[0]);
    self::assertInstanceOf(ConsumptionDeclaration::class, $mapper->collection('consumptions', [self::declarationRow()])[0]);
  }

  /**
   * Method unsupportedCollectionProjectionIsRejected
   *
   * Keeps the original failure for an unsupported nonempty projection.
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function unsupportedCollectionProjectionIsRejected(): void
  {
    $mapper = new InventoryRowMapper(new DateTimeZone('UTC'));
    $this->expectException(LogicException::class);
    $this->expectExceptionMessage('Unknown inventory projection.');

    $mapper->collection('unknown', [[]]);
  }

  /**
   * Method movementRow
   *
   * Supplies immutable facts with nullable price and operational links.
   *
   * @access private
   *
   * @param string $id the persisted movement identity
   *
   * @return array<string,mixed> the persisted movement fixture
   */
  private static function movementRow(string $id): array
  {
    return ['id' => $id, 'organization_id' => 'org', 'part_id' => 'part', 'warehouse_id' => 'warehouse', 'kind' => 'consumption', 'quantity' => '-0.123456', 'unit_cost' => null, 'total_value' => '0.000001', 'currency' => 'EUR', 'reason' => 'Original issue', 'actor_id' => 'actor', 'occurred_at' => '2026-10-08 12:34:56', 'intervention_id' => 'intervention', 'work_item_id' => null, 'equipment_id' => null, 'correction_of' => null, 'source_receipt_id' => null, 'late' => true];
  }

  /**
   * Method declarationRow
   *
   * Supplies the original pending declaration snapshot.
   *
   * @access private
   *
   * @return array<string,mixed> the persisted declaration fixture
   */
  private static function declarationRow(): array
  {
    return ['id' => 'declaration', 'organization_id' => 'org', 'part_id' => 'part', 'warehouse_id' => 'warehouse', 'quantity' => '0.123456', 'intervention_id' => 'intervention', 'work_item_id' => null, 'equipment_id' => null, 'actor_id' => 'actor', 'occurred_at' => '2026-10-08 12:34:56', 'status' => 'received_pending', 'reason' => 'insufficient_stock', 'movement_id' => null, 'late' => false];
  }
  // #endregion
}
