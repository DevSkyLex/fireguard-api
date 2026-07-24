<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\ValueObject;

use Approval\Domain\ValueObject\ApprovalStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalStatus::class)]
final class ApprovalStatusTest extends TestCase
{
  #[Test]
  public function testCasesHaveExpectedStringValues(): void
  {
    self::assertSame('pending', ApprovalStatus::PENDING->value);
    self::assertSame('approved', ApprovalStatus::APPROVED->value);
    self::assertSame('rejected', ApprovalStatus::REJECTED->value);
    self::assertSame('cancelled', ApprovalStatus::CANCELLED->value);
    self::assertSame('expired', ApprovalStatus::EXPIRED->value);
  }

  #[Test]
  public function testValuesReturnsEveryCaseValue(): void
  {
    self::assertSame(
      ['pending', 'approved', 'rejected', 'cancelled', 'expired'],
      ApprovalStatus::values(),
    );
  }

  #[Test]
  public function testFromResolvesBackToCase(): void
  {
    self::assertSame(ApprovalStatus::EXPIRED, ApprovalStatus::from('expired'));
  }
}
