<?php

declare(strict_types=1);

namespace Inventory\Infrastructure\Persistence\Doctrine\Mapper;

use DateTimeImmutable;
use DateTimeZone;
use Inventory\Application\Contract\Stock\InventoryOperationReceipt;
use Inventory\Domain\Model\Stock\{ConsumptionDeclaration, InventoryReference, StockBalance, StockMovement};
use LogicException;
use Shared\Domain\ValueObject\DecimalAmount;

use function is_array;
use function is_int;
use function is_string;
use function json_decode;

use const JSON_THROW_ON_ERROR;

/**
 * Class InventoryRowMapper
 *
 * Restores Inventory persistence rows without accessing a database or changing their values.
 *
 * @category Mapper
 */
final readonly class InventoryRowMapper
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Uses the repository's storage zone to interpret persisted wall-clock timestamps.
   *
   * @access public
   *
   * @param DateTimeZone $storageZone the same zone used for Inventory writes
   *
   * @return void
   */
  public function __construct(private DateTimeZone $storageZone)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method operationReceipt
   *
   * Retains the original replay response and its authorized operation scope.
   *
   * @access public
   *
   * @param string $org the queried organization
   * @param string $operationId the queried stable operation identity
   * @param array<string,mixed> $row the persisted receipt
   *
   * @return InventoryOperationReceipt the original operation snapshot
   */
  public function operationReceipt(string $org, string $operationId, array $row): InventoryOperationReceipt
  {
    $response = json_decode(self::s($row, 'response'), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($response)) {
      throw new LogicException('Invalid stored operation response.');
    }

    /** @var array<string,mixed> $response */
    return new InventoryOperationReceipt($org, $operationId, self::s($row, 'payload_hash'), $response);
  }

  /**
   * Method linkedValue
   *
   * Keeps a linked valuation unknown when any contributing value is absent.
   *
   * @access public
   *
   * @param array<string,mixed> $row the amount and unknown-value count aggregate
   *
   * @return string|null the exact six-place amount, or an unknown valuation
   */
  public function linkedValue(array $row): ?string
  {
    $unknown = $row['unknown'] ?? null;
    if (!is_int($unknown) && !is_string($unknown)) {
      throw new LogicException('Invalid linked valuation count.');
    }

    return 0 !== (int) $unknown ? null : DecimalAmount::fromString(self::s($row, 'amount'))->toString();
  }

  /**
   * Method reference
   *
   * Restores catalog fields while retaining archived references.
   *
   * @access public
   *
   * @param string $type the parts or warehouses collection
   * @param array<string,mixed> $row the persisted reference
   *
   * @return InventoryReference the catalog reference
   */
  public function reference(string $type, array $row): InventoryReference
  {
    return new InventoryReference(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'code'), self::s($row, 'label'), 'parts' === $type ? self::s($row, 'unit') : null, 'parts' === $type ? self::s($row, 'kind') : null, (bool) $row['archived']);
  }

  /**
   * Method balance
   *
   * Preserves exact quantities and nullable internal valuations.
   *
   * @access public
   *
   * @param array<string,mixed> $row the persisted balance
   *
   * @return StockBalance the current stock balance
   */
  public function balance(array $row): StockBalance
  {
    return new StockBalance(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'quantity'), self::n($row, 'total_value'), self::s($row, 'currency'));
  }

  /**
   * Method movement
   *
   * Restores immutable movement facts and nullable links in the storage zone.
   *
   * @access public
   *
   * @param array<string,mixed> $row the persisted movement
   *
   * @return StockMovement the original movement
   */
  public function movement(array $row): StockMovement
  {
    return new StockMovement(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'kind'), self::s($row, 'quantity'), self::n($row, 'unit_cost'), self::n($row, 'total_value'), self::s($row, 'currency'), self::s($row, 'reason'), self::s($row, 'actor_id'), new DateTimeImmutable(self::s($row, 'occurred_at'), $this->storageZone), self::n($row, 'intervention_id'), self::n($row, 'work_item_id'), self::n($row, 'equipment_id'), self::n($row, 'correction_of'), self::n($row, 'source_receipt_id'), (bool) $row['late']);
  }

  /**
   * Method declaration
   *
   * Restores the declaration's original quantity, operational scope and current resolution.
   *
   * @access public
   *
   * @param array<string,mixed> $row the persisted consumption declaration
   *
   * @return ConsumptionDeclaration the retained declaration
   */
  public function declaration(array $row): ConsumptionDeclaration
  {
    return new ConsumptionDeclaration(self::s($row, 'id'), self::s($row, 'organization_id'), self::s($row, 'part_id'), self::s($row, 'warehouse_id'), self::s($row, 'quantity'), self::s($row, 'intervention_id'), self::n($row, 'work_item_id'), self::n($row, 'equipment_id'), self::s($row, 'actor_id'), new DateTimeImmutable(self::s($row, 'occurred_at'), $this->storageZone), self::s($row, 'status'), self::n($row, 'reason'), self::n($row, 'movement_id'), (bool) $row['late']);
  }

  /**
   * Method collection
   *
   * Preserves the repository's SQL order when restoring a collection projection.
   *
   * @access public
   *
   * @param string $type the requested Inventory collection
   * @param list<array<string,mixed>> $rows the already ordered SQL result
   *
   * @return list<InventoryReference|StockBalance|StockMovement|ConsumptionDeclaration> the restored collection
   */
  public function collection(string $type, array $rows): array
  {
    $items = [];
    foreach ($rows as $row) {
      $items[] = match ($type) {
        'parts', 'warehouses' => $this->reference($type, $row),
        'balances' => $this->balance($row),
        'movements' => $this->movement($row),
        'consumptions' => $this->declaration($row),
        default => throw new LogicException('Unknown inventory projection.'),
      };
    }

    return $items;
  }

  /**
   * Method movements
   *
   * Preserves the repository's ordering of immutable intervention facts.
   *
   * @access public
   *
   * @param list<array<string,mixed>> $rows the already ordered movement rows
   *
   * @return list<StockMovement> the restored movement facts
   */
  public function movements(array $rows): array
  {
    $movements = [];
    foreach ($rows as $row) {
      $movements[] = $this->movement($row);
    }

    return $movements;
  }

  /**
   * Method s
   *
   * Rejects malformed persisted string fields instead of silently coercing them.
   *
   * @access private
   *
   * @param array<string,mixed> $r the persisted row
   * @param string $key the required field
   *
   * @return string the original field value
   */
  private static function s(array $r, string $key): string
  {
    if (!isset($r[$key]) || !is_string($r[$key])) {
      throw new LogicException('Malformed inventory persisted field ' . $key);
    }

    return $r[$key];
  }

  /**
   * Method n
   *
   * Keeps absent or null persisted values unknown without relaxing string validation.
   *
   * @access private
   *
   * @param array<string,mixed> $r the persisted row
   * @param string $key the nullable field
   *
   * @return string|null the original value or null
   */
  private static function n(array $r, string $key): ?string
  {
    return null === ($r[$key] ?? null) ? null : self::s($r, $key);
  }
  // #endregion
}
