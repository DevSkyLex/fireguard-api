<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\InspectionResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectionResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectionResult::class)]
final class InspectionResultTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['pass', 'fail', 'partial'], InspectionResult::values());
  }

  #[Test]
  public function itReportsPassState(): void
  {
    self::assertTrue(InspectionResult::PASS->isPass());
    self::assertFalse(InspectionResult::FAIL->isPass());
  }

  #[Test]
  public function itReportsFailState(): void
  {
    self::assertTrue(InspectionResult::FAIL->isFail());
    self::assertFalse(InspectionResult::PASS->isFail());
  }
}
