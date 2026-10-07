<?php

declare(strict_types=1);

namespace Inventory\Domain\Model\Stock;

use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\ValueObject\StockQuantity;
use Shared\Domain\ValueObject\DecimalAmount;

/** Nonnegative quantities and CUMP, with all remaining value allocated on the last issue. @category Model */
final readonly class StockValuation
{
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
    $known = '0.000000' === $this->quantity || null !== $this->totalValue;

    return new self(DecimalAmount::fromString($this->quantity)->add(DecimalAmount::fromString($quantity))->toString(), $known && null !== $value ? DecimalAmount::fromString($this->totalValue ?? '0.000000')->add(DecimalAmount::fromString($value))->toString() : null);
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

    return new StockIssue(new self($remaining, '0.000000' === $remaining ? '0.000000' : (null === $value ? null : DecimalAmount::fromString($this->totalValue ?? '0.000000')->subtract(DecimalAmount::fromString($value))->toString())), $value, null === $value ? null : DecimalAmount::fromString($value)->divide(DecimalAmount::fromString($quantity))->toString());
  }
}
