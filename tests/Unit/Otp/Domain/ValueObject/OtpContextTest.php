<?php

declare(strict_types=1);

namespace Tests\Unit\Otp\Domain\ValueObject;

use Otp\Domain\ValueObject\OtpContext;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OtpContextTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OtpContext::class)]
final class OtpContextTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testFromArrayFiltersNonStringKeys(): void
  {
    $context = OtpContext::fromArray([
      'transaction_id' => 'txn-123',
      'description' => 'Payment',
      'data' => [
        'amount' => 100,
        1 => 'ignored',
      ],
    ]);

    self::assertSame('txn-123', $context->transactionId);
    self::assertSame('Payment', $context->description);
    self::assertSame(['amount' => 100], $context->data);
  }

  #[Test]
  public function testToArrayAndIsEmpty(): void
  {
    $context = OtpContext::create(
      transactionId: 'txn-999',
      description: 'Reset',
      data: ['reason' => 'test'],
    );

    self::assertFalse($context->isEmpty());

    $data = $context->toArray();
    self::assertSame('txn-999', $data['transaction_id']);

    $empty = new OtpContext();
    self::assertTrue($empty->isEmpty());
  }
  // #endregion
}
