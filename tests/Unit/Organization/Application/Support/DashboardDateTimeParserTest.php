<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Support;

use InvalidArgumentException;
use Organization\Application\Support\DashboardDateTimeParser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardDateTimeParser::class)]
final class DashboardDateTimeParserTest extends TestCase
{
  #[Test]
  public function testParseNullableReturnsNullForEmptyValues(): void
  {
    self::assertNull(DashboardDateTimeParser::parseNullable(null, 'from'));
    self::assertNull(DashboardDateTimeParser::parseNullable('', 'from'));
  }

  #[Test]
  public function testParseNullableDelegatesToParse(): void
  {
    $parsed = DashboardDateTimeParser::parseNullable('2026-03-01T10:00:00Z', 'from');

    self::assertNotNull($parsed);
    self::assertSame('2026-03-01T10:00:00+00:00', $parsed->format('Y-m-d\TH:i:sP'));
  }

  #[Test]
  public function testParseSupportsFractionalSecondsAndExplicitOffset(): void
  {
    $parsed = DashboardDateTimeParser::parse('2026-03-01T10:00:00.123+02:00', 'to');

    self::assertSame('2026-03-01T10:00:00.123000+02:00', $parsed->format('Y-m-d\TH:i:s.uP'));
  }

  #[Test]
  public function testParseRejectsValuesWithoutExplicitTimezone(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid "from" datetime filter.');

    DashboardDateTimeParser::parse('2026-03-01T10:00:00', 'from');
  }

  #[Test]
  public function testParseRejectsWellFormedButImpossibleDateTimes(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid "to" datetime filter.');

    DashboardDateTimeParser::parse('2024-13-45T25:61:61+00:00', 'to');
  }
}
