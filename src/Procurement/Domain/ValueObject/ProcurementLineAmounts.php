<?php

declare(strict_types=1);

namespace Procurement\Domain\ValueObject;

use Procurement\Domain\Exception\ProcurementException;
use Shared\Domain\ValueObject\DecimalAmount;

/**
 * Class ProcurementLineAmounts
 *
 * Restores exact price and gross receipt/return quantities with their shared bounds enforced.
 *
 * @category ValueObject
 */
final readonly class ProcurementLineAmounts
{
  // #region Properties
  /**
   * Property quantity
   */
  public string $quantity;

  /**
   * Property unitCost
   */
  public ?string $unitCost;

  /**
   * Property receivedQuantity
   */
  public string $receivedQuantity;

  /**
   * Property returnedQuantity
   */
  public string $returnedQuantity;
  // #endregion

  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param string $quantity the positive exact ordered quantity
   * @param ?string $unitCost the exact nonnegative unit amount or unknown
   * @param string $receivedQuantity the retained gross received quantity
   * @param string $returnedQuantity the retained physical return
   *
   * @return void
   */
  public function __construct(string $quantity, ?string $unitCost, string $receivedQuantity = '0.000000', string $returnedQuantity = '0.000000')
  {
    $cost = null === $unitCost ? null : DecimalAmount::fromString($unitCost);
    if (null !== $cost && $cost->isNegative()) {
      throw ProcurementException::invalid('A procurement unit cost cannot be negative.');
    }
    $ordered = DecimalAmount::fromString($quantity);
    $received = DecimalAmount::fromString($receivedQuantity);
    $returned = DecimalAmount::fromString($returnedQuantity);
    self::assertQuantities($ordered, $received, $returned);
    $this->quantity = $ordered->toString();
    $this->unitCost = $cost?->toString();
    $this->receivedQuantity = $received->toString();
    $this->returnedQuantity = $returned->toString();
  }
  // #endregion

  // #region Methods
  /**
   * Method assertQuantities
   *
   * Keeps ordered, gross received and physically returned quantities bounded together.
   *
   * @access private
   *
   * @param DecimalAmount $ordered the positive bounded ordered quantity
   * @param DecimalAmount $received the retained gross delivery
   * @param DecimalAmount $returned the retained physical return
   *
   * @return void
   */
  private static function assertQuantities(DecimalAmount $ordered, DecimalAmount $received, DecimalAmount $returned): void
  {
    if ($ordered->isNegative() || $ordered->isZero() || $ordered->compareTo(DecimalAmount::fromInt(100000)) > 0 || $received->isNegative() || $received->compareTo($ordered) > 0 || $returned->isNegative() || $returned->compareTo($received) > 0) {
      throw ProcurementException::invalid('An ordered line needs a positive quantity up to 100000 and bounded receipt and return quantities.');
    }
  }

  // #endregion
}
