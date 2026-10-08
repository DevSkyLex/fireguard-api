<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\ValueObject\StockQuantity;
use Shared\Domain\ValueObject\DecimalAmount;

/** Nonnegative quantities and CUMP, with all remaining value allocated on the last issue. @category Model */
final readonly class StockValuation
{
  /**
   * Constant ZERO
   */
  private const string ZERO = '0.000000';

  public function __construct(public string $quantity, public ?string $totalValue)
  {
    if (DecimalAmount::fromString($quantity)->isNegative() || (null !== $totalValue && DecimalAmount::fromString($totalValue)->isNegative())) {
      throw new InventoryConflictException('Stock quantity and total value must remain nonnegative.');
    }
  }

  public function receive(string $quantity, ?string $value): self
  {
    $quantity = new StockQuantity($quantity)->value;
    if (null !== $value && DecimalAmount::fromString($value)->isNegative()) {
      throw new InventoryConflictException('Receipt valuation cannot be negative.');
    }
    $known = self::ZERO === $this->quantity || null !== $this->totalValue;

    return new self(DecimalAmount::fromString($this->quantity)->add(DecimalAmount::fromString($quantity))->toString(), $known && null !== $value ? DecimalAmount::fromString($this->totalValue ?? self::ZERO)->add(DecimalAmount::fromString($value))->toString() : null);
  }

  public function issue(string $quantity): StockIssue
  {
    $quantity = new StockQuantity($quantity)->value;
    if (DecimalAmount::fromString($this->quantity)->compareTo(DecimalAmount::fromString($quantity)) < 0) {
      throw new InventoryConflictException('Insufficient stock; no partial issue is allowed.');
    }
    $value = null;
    if (null !== $this->totalValue) {
      $value = 0 === DecimalAmount::fromString($this->quantity)->compareTo(DecimalAmount::fromString($quantity)) ? $this->totalValue : DecimalAmount::fromString($this->totalValue)->multiplyAndDivide(DecimalAmount::fromString($quantity), DecimalAmount::fromString($this->quantity))->toString();
      if (DecimalAmount::fromString($value)->compareTo(DecimalAmount::fromString($this->totalValue)) > 0) {
        $value = $this->totalValue;
      }
    }
    $remaining = DecimalAmount::fromString($this->quantity)->subtract(DecimalAmount::fromString($quantity))->toString();
    $remainingValue = null;
    if (self::ZERO === $remaining) {
      $remainingValue = self::ZERO;
    } elseif (null !== $value) {
      $remainingValue = DecimalAmount::fromString($this->totalValue ?? self::ZERO)->subtract(DecimalAmount::fromString($value))->toString();
    }

    return new StockIssue(new self($remaining, $remainingValue), $value, null === $value ? null : DecimalAmount::fromString($value)->divide(DecimalAmount::fromString($quantity))->toString());
  }
}
