<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\ValueObject;

use Approval\Domain\ValueObject\ApprovalRequestId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ApprovalRequestId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestId::class)]
final class ApprovalRequestIdTest extends TestCase
{
  private const string UUID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  #[Test]
  public function testFromStringBuildsValueObject(): void
  {
    $id = ApprovalRequestId::fromString(self::UUID);

    self::assertSame(self::UUID, $id->value);
    self::assertSame(self::UUID, (string) $id);
  }

  #[Test]
  public function testEqualsComparesByValue(): void
  {
    $a = ApprovalRequestId::fromString(self::UUID);
    $b = ApprovalRequestId::fromString(self::UUID);

    self::assertTrue($a->equals($b));
  }

  #[Test]
  public function testFromStringRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    ApprovalRequestId::fromString('not-a-uuid');
  }
}
