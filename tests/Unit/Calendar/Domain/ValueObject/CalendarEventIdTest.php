<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\ValueObject;

use Calendar\Domain\ValueObject\CalendarEventId;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test CalendarEventId.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventId::class)]
final class CalendarEventIdTest extends TestCase
{
  private const string VALID_UUID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itCreatesFromString(): void
  {
    $id = CalendarEventId::fromString(self::VALID_UUID);

    self::assertSame(self::VALID_UUID, $id->value);
    self::assertSame(self::VALID_UUID, (string) $id);
  }

  #[Test]
  public function itComparesEquality(): void
  {
    $a = CalendarEventId::fromString(self::VALID_UUID);
    $b = CalendarEventId::fromString(self::VALID_UUID);
    $c = CalendarEventId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64a02');

    self::assertTrue($a->equals($b));
    self::assertFalse($a->equals($c));
  }

  #[Test]
  public function itRejectsInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    CalendarEventId::fromString('not-a-uuid');
  }
}
