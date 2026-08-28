<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Ical;

use Calendar\Application\Contract\Feed\CalendarFeedItem;
use DateTimeImmutable;
use DateTimeZone;

use function implode;
use function mb_strlen;
use function mb_strtoupper;
use function mb_substr;
use function rtrim;
use function sprintf;
use function str_replace;
use function strlen;

/**
 * Ical CalendarFeedIcalWriter.
 *
 * Hand-written RFC 5545 serializer for the member feed — deliberately no
 * Composer dependency for a format this small. Covers exactly what a
 * subscribing client (Outlook, Google Calendar, Apple Calendar) needs:
 * VCALENDAR/VEVENT with a stable UID per entry, DTSTART/DTEND (date-only for
 * all-day items, UTC datetimes otherwise), a type-prefixed SUMMARY, a short
 * DESCRIPTION, and a deep URL into the app's calendar page. Implements the
 * TEXT escaping rules (backslash, semicolon, comma, newline) and the 75-octet
 * line folding of RFC 5545 §3.1, folding on UTF-8 character boundaries.
 *
 * @category Ical
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CalendarFeedIcalWriter
{
  // #region Constants
  /**
   * Constant SOURCE_LABELS.
   *
   * Human-readable SUMMARY prefixes per feed source key.
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  private const array SOURCE_LABELS = [
    'calendar_event' => 'Événement',
    'inspection' => 'Inspection',
    'intervention' => 'Intervention',
    'maintenance' => 'Maintenance',
  ];

  /**
   * Constant DESCRIPTION_MAX_LENGTH.
   *
   * Longest DESCRIPTION carried into the feed, in characters.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DESCRIPTION_MAX_LENGTH = 300;

  /**
   * Constant CRLF.
   *
   * RFC 5545 content lines are CRLF-delimited.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string CRLF = "\r\n";
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $frontendUrl the public frontend base URL, for the per-event deep links
   */
  public function __construct(
    private string $frontendUrl,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method write.
   *
   * Serializes the feed items into a complete iCalendar document.
   *
   * @since 1.0.0
   *
   * @param list<CalendarFeedItem> $items the merged feed items
   * @param string $organizationId the organization the feed belongs to
   * @param DateTimeImmutable $generatedAt the generation timestamp (used as DTSTAMP)
   *
   * @return string the iCalendar document (CRLF line endings)
   */
  public function write(array $items, string $organizationId, DateTimeImmutable $generatedAt): string
  {
    $lines = [
      'BEGIN:VCALENDAR',
      'VERSION:2.0',
      'PRODID:-//FireGuard//Calendar Feed//FR',
      'CALSCALE:GREGORIAN',
      'METHOD:PUBLISH',
      $this->fold('X-WR-CALNAME:' . $this->escape('FireGuard')),
    ];

    $dtStamp = $this->formatUtc($generatedAt);

    foreach ($items as $item) {
      $lines[] = 'BEGIN:VEVENT';
      $lines[] = $this->fold(sprintf('UID:%s-%s@fireguard', $item->sourceKey, $item->id));
      $lines[] = 'DTSTAMP:' . $dtStamp;
      if ($item->allDay) {
        $lines[] = 'DTSTART;VALUE=DATE:' . $item->startsAt->format('Ymd');
        if (null !== $item->endsAt) {
          // RFC 5545: an all-day DTEND is non-inclusive — add one day.
          $lines[] = 'DTEND;VALUE=DATE:' . $item->endsAt->modify('+1 day')->format('Ymd');
        }
      } else {
        $lines[] = 'DTSTART:' . $this->formatUtc($item->startsAt);
        if (null !== $item->endsAt) {
          $lines[] = 'DTEND:' . $this->formatUtc($item->endsAt);
        }
      }
      $lines[] = $this->fold('SUMMARY:' . $this->escape(sprintf(
        '[%s] %s',
        self::SOURCE_LABELS[$item->sourceKey] ?? mb_strtoupper($item->sourceKey),
        $item->title,
      )));
      $description = $this->shorten($item->description);
      if (null !== $description) {
        $lines[] = $this->fold('DESCRIPTION:' . $this->escape($description));
      }
      if (null !== $item->status) {
        $lines[] = $this->fold('X-FIREGUARD-STATUS:' . $this->escape($item->status));
      }
      $lines[] = $this->fold('URL:' . sprintf(
        '%s/organizations/%s/calendar?target=%s&id=%s',
        rtrim($this->frontendUrl, '/'),
        $organizationId,
        $item->targetType,
        $item->targetId,
      ));
      $lines[] = 'END:VEVENT';
    }

    $lines[] = 'END:VCALENDAR';

    return implode(self::CRLF, $lines) . self::CRLF;
  }

  /**
   * Method formatUtc.
   *
   * Formats a datetime as an RFC 5545 UTC DATE-TIME (`Ymd\THis\Z`).
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $dateTime the datetime to format
   *
   * @return string the UTC iCalendar representation
   */
  private function formatUtc(DateTimeImmutable $dateTime): string
  {
    return $dateTime->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
  }

  /**
   * Method escape.
   *
   * Applies the RFC 5545 §3.3.11 TEXT escaping: backslash first, then
   * semicolon, comma, and newlines (CRLF and bare LF both become `\n`).
   *
   * @since 1.0.0
   *
   * @param string $value the raw text
   *
   * @return string the escaped iCalendar TEXT value
   */
  private function escape(string $value): string
  {
    $value = str_replace('\\', '\\\\', $value);
    $value = str_replace(';', '\\;', $value);
    $value = str_replace(',', '\\,', $value);

    return str_replace(["\r\n", "\n", "\r"], '\\n', $value);
  }

  /**
   * Method shorten.
   *
   * Trims a description to a subscription-friendly length.
   *
   * @since 1.0.0
   *
   * @param ?string $value the raw description
   *
   * @return ?string the shortened description, or null when empty
   */
  private function shorten(?string $value): ?string
  {
    if (null === $value || '' === $value) {
      return null;
    }

    return mb_strlen($value) > self::DESCRIPTION_MAX_LENGTH
      ? mb_substr($value, 0, self::DESCRIPTION_MAX_LENGTH - 1) . '…'
      : $value;
  }

  /**
   * Method fold.
   *
   * Folds a content line at 75 octets per RFC 5545 §3.1, continuing with
   * CRLF followed by a single space. Splits on UTF-8 character boundaries
   * so a multibyte character is never cut in half.
   *
   * @since 1.0.0
   *
   * @param string $line the unfolded content line
   *
   * @return string the folded content line
   */
  private function fold(string $line): string
  {
    if (strlen($line) <= 75) {
      return $line;
    }

    $out = [];
    $current = '';
    $currentBytes = 0;
    $limit = 75;
    $length = mb_strlen($line);

    for ($i = 0; $i < $length; ++$i) {
      $char = mb_substr($line, $i, 1);
      $charBytes = strlen($char);
      if ($currentBytes + $charBytes > $limit) {
        $out[] = $current;
        // Continuation lines start with one space, which counts in the 75 octets.
        $current = ' ';
        $currentBytes = 1;
        $limit = 75;
      }
      $current .= $char;
      $currentBytes += $charBytes;
    }

    if ('' !== $current && ' ' !== $current) {
      $out[] = $current;
    }

    return implode(self::CRLF, $out);
  }
  // #endregion
}
