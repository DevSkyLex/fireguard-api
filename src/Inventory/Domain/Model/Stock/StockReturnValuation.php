<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use Inventory\Domain\Exception\InventoryConflictException;
use Shared\Domain\ValueObject\DecimalAmount;

/**
 * Class StockReturnValuation
 *
 * Allocates the remaining original value so rounded partial returns never overcredit it.
 *
 * @category Model
 */
final readonly class StockReturnValuation
{
  // #region Methods
  /**
   * Method allocate
   *
   * Gives the last return the exact residual and keeps unknown original or prior values unknown.
   * Callers must validate the cumulative return quantity against the original before allocating its value.
   *
   * @access public
   *
   * @param StockMovement $original the immutable source movement
   * @param string $quantity the current positive return quantity
   * @param string $returnedQuantity the positive quantity returned before this operation
   * @param string|null $linkedValue the total of prior returns, retaining unknown costs
   *
   * @return string|null the allocated value, retaining unknown costs
   */
  public static function allocate(StockMovement $original, string $quantity, string $returnedQuantity, ?string $linkedValue): ?string
  {
    $originalValue = null === $original->totalValue ? null : DecimalAmount::fromString($original->totalValue);
    if (null !== $originalValue && $originalValue->isNegative()) {
      $originalValue = DecimalAmount::zero()->subtract($originalValue);
    }
    if (null === $originalValue || null === $linkedValue) {
      return null;
    }
    $remainingValue = $originalValue->subtract(DecimalAmount::fromString($linkedValue));
    if ($remainingValue->isNegative()) {
      throw new InventoryConflictException('Original stock valuation is inconsistent.');
    }
    $originalQuantity = DecimalAmount::fromString($original->quantity);
    if ($originalQuantity->isNegative()) {
      $originalQuantity = DecimalAmount::zero()->subtract($originalQuantity);
    }
    $returned = DecimalAmount::fromString($returnedQuantity);
    $currentQuantity = DecimalAmount::fromString($quantity);
    $lastReturn = 0 === $returned->add($currentQuantity)->compareTo($originalQuantity);
    $value = $lastReturn ? $remainingValue->toString() : $remainingValue->multiplyAndDivide($currentQuantity, $originalQuantity->subtract($returned))->toString();
    if (DecimalAmount::fromString($value)->isNegative()) {
      throw new InventoryConflictException('Original stock valuation is inconsistent.');
    }

    return $value;
  }
  // #endregion
}
