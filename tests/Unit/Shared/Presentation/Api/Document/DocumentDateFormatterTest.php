<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Presentation\Api\Document;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;
use Shared\Presentation\Api\Document\DocumentDateFormatter;

/**
 * Test DocumentDateFormatterTest.
 *
 * The dates printed in generated PDFs follow the organization regional
 * settings — timezone and date format pattern — so every supported pattern
 * has to map correctly, the timezone conversion has to be real, and bad
 * input must never destroy data.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DocumentDateFormatter::class)]
final class DocumentDateFormatterTest extends TestCase
{
  /**
   * @return array<string, array{0: string, 1: string}>
   */
  public static function dateFormatCatalog(): array
  {
    return [
      'dd/MM/yyyy' => ['dd/MM/yyyy', '01/06/2026'],
      'MM/dd/yyyy' => ['MM/dd/yyyy', '06/01/2026'],
      'yyyy-MM-dd' => ['yyyy-MM-dd', '2026-06-01'],
      'dd.MM.yyyy' => ['dd.MM.yyyy', '01.06.2026'],
      'dd-MM-yyyy' => ['dd-MM-yyyy', '01-06-2026'],
    ];
  }

  #[Test]
  #[DataProvider('dateFormatCatalog')]
  public function testItFormatsEverySupportedDateFormatPattern(string $pattern, string $expected): void
  {
    $formatter = new DocumentDateFormatter($pattern, 'UTC');

    self::assertSame($expected, $formatter->formatDate('2026-06-01T08:00:00+00:00'));
  }

  #[Test]
  public function testItConvertsToTheOrganizationTimezone(): void
  {
    // 2026-06-01 23:30 UTC is already 2026-06-02 in Paris (CEST, UTC+2).
    $formatter = new DocumentDateFormatter('dd/MM/yyyy', 'Europe/Paris');

    self::assertSame('02/06/2026', $formatter->formatDate('2026-06-01T23:30:00+00:00'));
    self::assertSame('02/06/2026 01:30', $formatter->formatDateTime('2026-06-01T23:30:00+00:00'));
  }

  #[Test]
  public function testItAppendsATwentyFourHourTimeForDateTimes(): void
  {
    $formatter = new DocumentDateFormatter('MM/dd/yyyy', 'UTC');

    self::assertSame('06/01/2026 08:05', $formatter->formatDateTime('2026-06-01T08:05:00+00:00'));
  }

  #[Test]
  public function testItAcceptsADateTimeImmutableInput(): void
  {
    $formatter = new DocumentDateFormatter('yyyy-MM-dd', 'UTC');

    self::assertSame('2026-06-01', $formatter->formatDate(new DateTimeImmutable('2026-06-01T08:00:00+00:00')));
  }

  #[Test]
  public function testItReturnsNullForNullOrEmptyInput(): void
  {
    $formatter = new DocumentDateFormatter('dd/MM/yyyy', 'UTC');

    self::assertNull($formatter->formatDate(null));
    self::assertNull($formatter->formatDate(''));
    self::assertNull($formatter->formatDateTime(null));
  }

  #[Test]
  public function testItReturnsAnUnparseableStringUnchanged(): void
  {
    $formatter = new DocumentDateFormatter('dd/MM/yyyy', 'UTC');

    self::assertSame('not-a-date', $formatter->formatDate('not-a-date'));
  }

  #[Test]
  public function testItFallsBackToIsoForAnUnknownPatternAndUtcForABadTimezone(): void
  {
    $formatter = new DocumentDateFormatter('unknown-pattern', 'Not/AZone');

    self::assertSame('2026-06-01', $formatter->formatDate('2026-06-01T08:00:00+00:00'));
  }
}
