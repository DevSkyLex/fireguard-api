<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\DecimalAmount;

use function array_is_list;
use function array_values;
use function count;

/**
 * Class PurchaseOrderLines
 *
 * Owns immutable ordered snapshots and derives gross receipt lifecycle without reopening delivery.
 *
 * @category ValueObject
 */
final readonly class PurchaseOrderLines
{
  // #region Properties
  /**
   * Property values
   *
   * @var list<ProcurementLine> */
  private array $values;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param array<array-key,mixed> $lines the candidate ordered snapshots
   *
   * @return void
   */
  public function __construct(array $lines)
  {
    $this->values = self::validateLines($lines);
  }
  // #endregion

  // #region Methods
  /**
   * Method values
   *
   * @access public
   *
   * @return list<ProcurementLine> the retained snapshots
   */
  public function values(): array
  {
    return $this->values;
  }

  /**
   * Method receive
   *
   * @access public
   *
   * @param string $lineId the retained line UUID
   * @param string $quantity the positive exact received quantity
   *
   * @return self the fully validated replacement collection
   */
  public function receive(string $lineId, string $quantity): self
  {
    $index = $this->lineIndex($lineId);
    $lines = $this->values;
    $lines[$index] = $lines[$index]->receive($quantity);

    return new self(array_values($lines));
  }

  /**
   * Method returnReceived
   *
   * @access public
   *
   * @param string $lineId the retained line UUID
   * @param string $quantity the positive exact returned quantity
   *
   * @return self the fully validated replacement collection
   */
  public function returnReceived(string $lineId, string $quantity): self
  {
    $index = $this->lineIndex($lineId);
    $lines = $this->values;
    $lines[$index] = $lines[$index]->returnReceived($quantity);

    return new self(array_values($lines));
  }

  /**
   * Method receivedStatus
   *
   * @access public
   *
   * @return PurchaseOrderStatus the lifecycle derived from gross receipts
   */
  public function receivedStatus(): PurchaseOrderStatus
  {
    $received = DecimalAmount::zero();
    $remaining = DecimalAmount::zero();
    foreach ($this->values as $line) {
      $received = $received->add(DecimalAmount::fromString($line->receivedQuantity));
      $remaining = $remaining->add(DecimalAmount::fromString($line->remainingQuantity()));
    }

    if ($received->isZero()) {
      return PurchaseOrderStatus::ORDERED;
    }

    return $remaining->isZero() ? PurchaseOrderStatus::RECEIVED : PurchaseOrderStatus::PARTIAL_RECEIVED;
  }

  /**
   * Method lineIndex
   *
   * @access private
   *
   * @param string $lineId the retained line UUID
   *
   * @return int its current list index
   */
  private function lineIndex(string $lineId): int
  {
    foreach ($this->values as $index => $line) {
      if ($line->id === $lineId) {
        return $index;
      }
    }

    throw ProcurementException::invalid('The order does not contain this line.');
  }

  /**
   * Method validateLines
   *
   * @access private
   *
   * @param array<array-key, mixed> $lines the raw candidate line snapshots
   *
   * @return list<ProcurementLine> the bounded list with unique identities
   */
  private static function validateLines(array $lines): array
  {
    if (!array_is_list($lines) || count($lines) > 500) {
      throw ProcurementException::invalid('A purchase order needs a list of at most 500 lines.');
    }

    $identities = [];
    $validated = [];
    foreach ($lines as $line) {
      if (!$line instanceof ProcurementLine || isset($identities[$line->id])) {
        throw ProcurementException::invalid('Purchase-order lines must have unique validated identities.');
      }
      $identities[$line->id] = true;
      $validated[] = $line;
    }

    return $validated;
  }

  // #endregion
}
