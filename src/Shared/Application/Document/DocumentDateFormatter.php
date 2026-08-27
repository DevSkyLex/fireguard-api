<?php

declare(strict_types=1);

namespace Shared\Application\Document;

use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * Service DocumentDateFormatter.
 *
 * Formats the dates printed in generated documents (PDF exports) according
 * to an organization's regional settings: the ISO-8601-ish input strings the
 * read models carry are converted to the organization timezone and rendered
 * with the organization's date format pattern. An unparseable input is
 * returned unchanged — a document must never lose data over formatting.
 *
 * @category Service
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DocumentDateFormatter
{
  // #region Constants
  /**
   * Constant DATE_FORMAT_MAP.
   *
   * Maps the organization date format catalog
   * (`OrganizationRegionalSettings::ALLOWED_DATE_FORMATS`, ICU-style) to PHP
   * date() patterns.
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  private const array DATE_FORMAT_MAP = [
    'dd/MM/yyyy' => 'd/m/Y',
    'MM/dd/yyyy' => 'm/d/Y',
    'yyyy-MM-dd' => 'Y-m-d',
    'dd.MM.yyyy' => 'd.m.Y',
    'dd-MM-yyyy' => 'd-m-Y',
  ];

  private const string FALLBACK_DATE_FORMAT = 'Y-m-d';

  private const string TIME_FORMAT = 'H:i';
  // #endregion

  // #region Properties
  /**
   * The resolved PHP date pattern.
   *
   * @since 1.0.0
   */
  private string $phpDateFormat;

  /**
   * The target timezone.
   *
   * @since 1.0.0
   */
  private DateTimeZone $timezone;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $dateFormat the organization date format pattern (e.g. "dd/MM/yyyy")
   * @param string $timezone the IANA timezone identifier (e.g. "Europe/Paris")
   */
  public function __construct(string $dateFormat, string $timezone)
  {
    $this->phpDateFormat = self::DATE_FORMAT_MAP[$dateFormat] ?? self::FALLBACK_DATE_FORMAT;

    try {
      $zone = new DateTimeZone($timezone);
    } catch (Exception) {
      $zone = new DateTimeZone('UTC');
    }

    $this->timezone = $zone;
  }
  // #endregion

  // #region Methods
  /**
   * Method formatDate.
   *
   * Formats a date-only value in the organization timezone and date format.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|string|null $value the value to format
   *
   * @return ?string the formatted date, the raw string when unparseable, or null for null input
   */
  public function formatDate(DateTimeImmutable|string|null $value): ?string
  {
    return $this->format($value, $this->phpDateFormat);
  }

  /**
   * Method formatDateTime.
   *
   * Formats a date-and-time value in the organization timezone, using the
   * organization date format followed by a 24-hour time.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|string|null $value the value to format
   *
   * @return ?string the formatted date and time, the raw string when unparseable, or null for null input
   */
  public function formatDateTime(DateTimeImmutable|string|null $value): ?string
  {
    return $this->format($value, $this->phpDateFormat . ' ' . self::TIME_FORMAT);
  }

  /**
   * Method format.
   *
   * Applies one PHP pattern to a value after converting it to the target
   * timezone.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|string|null $value the value to format
   * @param string $pattern the PHP date() pattern
   *
   * @return ?string the formatted value, the raw string when unparseable, or null for null input
   */
  private function format(DateTimeImmutable|string|null $value, string $pattern): ?string
  {
    if (null === $value) {
      return null;
    }

    if ('' === $value) {
      return null;
    }

    if (!$value instanceof DateTimeImmutable) {
      try {
        $value = new DateTimeImmutable($value);
      } catch (Exception) {
        return $value;
      }
    }

    return $value->setTimezone($this->timezone)->format($pattern);
  }
  // #endregion
}
