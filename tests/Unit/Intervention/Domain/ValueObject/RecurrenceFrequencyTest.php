<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\ValueObject;

use Intervention\Domain\ValueObject\RecurrenceFrequency;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RecurrenceFrequency.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RecurrenceFrequency::class)]
final class RecurrenceFrequencyTest extends TestCase
{
  #[Test]
  public function testBackingValuesAreStable(): void
  {
    self::assertSame('weekly', RecurrenceFrequency::WEEKLY->value);
    self::assertSame('monthly', RecurrenceFrequency::MONTHLY->value);
    self::assertSame('quarterly', RecurrenceFrequency::QUARTERLY->value);
    self::assertSame('semiannual', RecurrenceFrequency::SEMIANNUAL->value);
    self::assertSame('annual', RecurrenceFrequency::ANNUAL->value);
  }

  #[Test]
  public function testValuesReturnsEveryBackingValueInDeclarationOrder(): void
  {
    self::assertSame(
      ['weekly', 'monthly', 'quarterly', 'semiannual', 'annual'],
      RecurrenceFrequency::values(),
    );
  }
}
