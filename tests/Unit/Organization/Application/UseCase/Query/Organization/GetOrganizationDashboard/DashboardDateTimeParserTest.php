<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard;

use InvalidArgumentException;
use Organization\Application\Support\DashboardDateTimeParser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(DashboardDateTimeParser::class)]
final class DashboardDateTimeParserTest extends TestCase
{
  #[Test]
  public function testParseAcceptsIso8601DatetimesWithExplicitTimezoneOffset(): void
  {
    $dateTime = DashboardDateTimeParser::parse('2026-03-30T10:15:30.123Z', 'from');

    self::assertSame('2026-03-30T10:15:30+00:00', $dateTime->format('c'));
    self::assertSame('123000', $dateTime->format('u'));
  }

  #[Test]
  public function testParseRejectsDatetimesWithoutExplicitTimezoneOffset(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid "from" datetime filter.');

    DashboardDateTimeParser::parse('2026-03-30T10:15:30', 'from');
  }

  #[Test]
  public function testParseRejectsRelativeDateStrings(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Invalid "from" datetime filter.');

    DashboardDateTimeParser::parse('tomorrow', 'from');
  }
}
