<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Presentation\Api\Ical;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use Calendar\Presentation\Api\Ical\CalendarFeedIcalWriter;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function explode;
use function mb_check_encoding;
use function str_replace;
use function strlen;
use function substr_count;

/**
 * Test CalendarFeedIcalWriterTest.
 *
 * Structural lint of the hand-written RFC 5545 output: document framing,
 * CRLF delimiters, TEXT escaping, stable UIDs, all-day DATE values, the
 * 75-octet folding, and the deep URL.
 *
 * @category Presentation Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarFeedIcalWriter::class)]
final class CalendarFeedIcalWriterTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64a01';

  #[Test]
  public function itFramesAValidEmptyCalendar(): void
  {
    $document = $this->writer()->write([], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $document);
    self::assertStringEndsWith("END:VCALENDAR\r\n", $document);
    self::assertStringContainsString("VERSION:2.0\r\n", $document);
    self::assertStringContainsString('PRODID:', $document);
    self::assertStringNotContainsString('BEGIN:VEVENT', $document);
  }

  #[Test]
  public function itWritesATimedEventWithStableUidTypedSummaryAndDeepUrl(): void
  {
    $document = $this->writer()->write([$this->item()], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertSame(1, substr_count($document, 'BEGIN:VEVENT'));
    self::assertSame(1, substr_count($document, 'END:VEVENT'));
    self::assertStringContainsString('UID:inspection-insp-1@fireguard', $document);
    self::assertStringContainsString("DTSTAMP:20260828T100000Z\r\n", $document);
    self::assertStringContainsString("DTSTART:20260901T080000Z\r\n", $document);
    self::assertStringContainsString("DTEND:20260901T100000Z\r\n", $document);
    self::assertStringContainsString('SUMMARY:[Inspection] Extincteurs hall A', $document);
    self::assertStringContainsString('DESCRIPTION:Contr', $document);
    // The URL line exceeds 75 octets and is folded — unfold before asserting.
    $unfolded = str_replace("\r\n ", '', $document);
    self::assertStringContainsString(
      'URL:https://app.example/organizations/' . self::ORGANIZATION_ID . '/calendar?target=inspection&id=insp-1',
      $unfolded,
    );
  }

  #[Test]
  public function itEscapesCommasSemicolonsBackslashesAndNewlines(): void
  {
    $item = $this->item(
      title: 'Salle A; aile B, c\\d',
      description: "ligne 1\nligne 2",
    );

    $document = $this->writer()->write([$item], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertStringContainsString('SUMMARY:[Inspection] Salle A\\; aile B\\, c\\\\d', $document);
    self::assertStringContainsString('DESCRIPTION:ligne 1\\nligne 2', $document);
  }

  #[Test]
  public function itWritesAllDayEventsAsDateValuesWithNonInclusiveEnd(): void
  {
    $item = $this->item(
      allDay: true,
      startsAt: new DateTimeImmutable('2026-09-01T00:00:00+00:00'),
      endsAt: new DateTimeImmutable('2026-09-02T00:00:00+00:00'),
    );

    $document = $this->writer()->write([$item], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertStringContainsString("DTSTART;VALUE=DATE:20260901\r\n", $document);
    // RFC 5545: DTEND is non-inclusive for all-day events — one day is added.
    self::assertStringContainsString("DTEND;VALUE=DATE:20260903\r\n", $document);
  }

  #[Test]
  public function itFoldsEveryContentLineTo75OctetsOrLess(): void
  {
    $item = $this->item(
      title: 'Très longue vérification périodique des extincteurs à poudre polyvalente du bâtiment principal — été 2026',
      description: 'Une description délibérément interminable pour forcer le pliage de ligne du sérialiseur iCalendar au-delà de soixante-quinze octets, accents compris.',
    );

    $document = $this->writer()->write([$item], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    foreach (explode("\r\n", $document) as $line) {
      self::assertLessThanOrEqual(75, strlen($line), 'RFC 5545 content lines must be at most 75 octets: ' . $line);
    }

    // Folded continuations start with a single space.
    self::assertStringContainsString("\r\n ", $document);
    // The document remains valid UTF-8 (no multibyte character was cut).
    self::assertTrue((bool) mb_check_encoding($document, 'UTF-8'));
  }

  #[Test]
  public function itCarriesTheRawStatusAsAPrivateProperty(): void
  {
    $document = $this->writer()->write([$this->item(status: 'overdue')], self::ORGANIZATION_ID, new DateTimeImmutable('2026-08-28T10:00:00+00:00'));

    self::assertStringContainsString('X-FIREGUARD-STATUS:overdue', $document);
  }

  /**
   * Method writer.
   *
   * @return CalendarFeedIcalWriter a writer bound to a fixture frontend URL
   */
  private function writer(): CalendarFeedIcalWriter
  {
    return new CalendarFeedIcalWriter('https://app.example/');
  }

  /**
   * Method item.
   *
   * @param string $title the item title
   * @param ?string $description the item description
   * @param bool $allDay whether the item is all-day
   * @param ?DateTimeImmutable $startsAt the item start
   * @param ?DateTimeImmutable $endsAt the item end
   * @param ?string $status the raw status
   *
   * @return CalendarFeedItem a feed item fixture
   */
  private function item(
    string $title = 'Extincteurs hall A',
    ?string $description = 'Contrôle semestriel',
    bool $allDay = false,
    ?DateTimeImmutable $startsAt = null,
    ?DateTimeImmutable $endsAt = null,
    ?string $status = null,
  ): CalendarFeedItem {
    return new CalendarFeedItem(
      sourceKey: 'inspection',
      id: 'insp-1',
      title: $title,
      description: $description,
      startsAt: $startsAt ?? new DateTimeImmutable('2026-09-01T08:00:00+00:00'),
      endsAt: $endsAt ?? ($allDay ? null : new DateTimeImmutable('2026-09-01T10:00:00+00:00')),
      allDay: $allDay,
      facilityId: null,
      status: $status,
      targetType: 'inspection',
      targetId: 'insp-1',
    );
  }
}
