<?php

declare(strict_types=1);

namespace Tests\Unit\Procurement\Domain\ValueObject;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Procurement\Domain\ValueObject\PurchaseOrderStatus;

/**
 * Class PurchaseOrderStatusTest
 *
 * Verifies stable lifecycle codes and reception gates.
 *
 * @category Tests
 */
#[CoversClass(PurchaseOrderStatus::class)]
final class PurchaseOrderStatusTest extends TestCase
{
  // #region Methods
  /**
   * Method testOnlyOutstandingOrderedGoodsCanBeReceived
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testOnlyOutstandingOrderedGoodsCanBeReceived(): void
  {
    self::assertSame(['draft', 'ordered', 'partial_received', 'received', 'cancelled'], PurchaseOrderStatus::values());
    self::assertFalse(PurchaseOrderStatus::DRAFT->allowsReceipt());
    self::assertTrue(PurchaseOrderStatus::ORDERED->allowsReceipt());
    self::assertTrue(PurchaseOrderStatus::PARTIAL_RECEIVED->allowsReceipt());
    self::assertFalse(PurchaseOrderStatus::RECEIVED->allowsReceipt());
    self::assertFalse(PurchaseOrderStatus::CANCELLED->allowsReceipt());
  }
  // #endregion
}
