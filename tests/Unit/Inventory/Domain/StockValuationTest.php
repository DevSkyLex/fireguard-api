<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory\Domain;

use InvalidArgumentException;
use Inventory\Domain\Exception\InventoryConflictException;
use Inventory\Domain\Model\Stock\StockValuation;
use Inventory\Domain\ValueObject\StockQuantity;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Exact weighted-average allocations and incomplete value semantics. @category Unit Tests */
final class StockValuationTest extends TestCase
{
  #[Test]
  public function mixedReceiptCostsAreAllocatedAndTheLastPieceKeepsTheResidual(): void
  {
    $balance = new StockValuation('0.000000', '0.000000')->receive('1.000000', '1.000000')->receive('2.000000', '4.000000');
    self::assertSame('3.000000', $balance->quantity);
    self::assertSame('5.000000', $balance->totalValue);
    $first = $balance->issue('1.000000');
    self::assertSame('1.666667', $first->totalValue);
    $second = $first->balance->issue('1.000000');
    self::assertSame('1.666667', $second->totalValue);
    $last = $second->balance->issue('1.000000');
    self::assertSame('1.666666', $last->totalValue);
    self::assertSame('0.000000', $last->balance->quantity);
    self::assertSame('0.000000', $last->balance->totalValue);
  }

  #[Test]
  public function fractionalQuantityDoesNotRoundTheIntermediateProduct(): void
  {
    $issue = new StockValuation('0.000003', '0.000001')->issue('0.000001');
    self::assertSame('0.000000', $issue->totalValue);
    self::assertSame('0.000001', $issue->balance->totalValue);
    $last = $issue->balance->issue('0.000002');
    self::assertSame('0.000001', $last->totalValue);
  }

  #[Test]
  public function unknownReceiptKeepsTheBalanceIncompleteUntilItIsEmptied(): void
  {
    $mixed = new StockValuation('1.000000', null)->receive('1.000000', '8.000000');
    self::assertNull($mixed->totalValue);
    $issue = $mixed->issue('1.000000');
    self::assertNull($issue->totalValue);
    $empty = $issue->balance->issue('1.000000');
    self::assertNull($empty->totalValue);
    self::assertSame('0.000000', $empty->balance->totalValue);
    self::assertSame('2.000000', $empty->balance->receive('1.000000', '2.000000')->totalValue);
  }

  #[Test]
  public function shortagesRefuseTheWholeIssue(): void
  {
    $this->expectException(InventoryConflictException::class);
    new StockValuation('1.000000', '2.000000')->issue('2.000000');
  }

  #[Test]
  public function quantitiesAreCanonicalStringsWithSixPlaces(): void
  {
    self::assertSame('1.230000', new StockQuantity('1.23')->value);
    self::assertSame('-1.000000', new StockQuantity('-1', true)->value);
    $this->expectException(InvalidArgumentException::class);
    new StockQuantity('0.000000');
  }
}
