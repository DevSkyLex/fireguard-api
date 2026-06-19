<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;

use function in_array;
use function is_string;
use function sprintf;
use function timezone_identifiers_list;
use function trim;

/**
 * ValueObject OrganizationRegionalSettings.
 *
 * Organization-wide regional and formatting preferences (timezone, locale, date
 * format, first day of week, measurement system). Values are validated against
 * a fixed catalog so the rest of the application can rely on them.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRegionalSettings
{
  // #region Constants
  /**
   * Supported display locales.
   *
   * @var list<string>
   */
  public const array ALLOWED_LOCALES = [
    'en-US',
    'en-GB',
    'fr-FR',
    'de-DE',
    'es-ES',
    'it-IT',
    'nl-NL',
    'pt-PT',
  ];

  /**
   * Supported date format patterns.
   *
   * @var list<string>
   */
  public const array ALLOWED_DATE_FORMATS = [
    'dd/MM/yyyy',
    'MM/dd/yyyy',
    'yyyy-MM-dd',
    'dd.MM.yyyy',
    'dd-MM-yyyy',
  ];

  /**
   * Supported first-day-of-week values.
   *
   * @var list<string>
   */
  public const array ALLOWED_FIRST_DAYS = ['monday', 'sunday'];

  /**
   * Supported measurement systems.
   *
   * @var list<string>
   */
  public const array ALLOWED_MEASUREMENT_SYSTEMS = ['metric', 'imperial'];

  private const string DEFAULT_TIMEZONE = 'UTC';

  private const string DEFAULT_LOCALE = 'en-US';

  private const string DEFAULT_DATE_FORMAT = 'yyyy-MM-dd';

  private const string DEFAULT_FIRST_DAY = 'monday';

  private const string DEFAULT_MEASUREMENT_SYSTEM = 'metric';
  // #endregion

  // #region Properties
  /**
   * IANA timezone identifier (e.g. "Europe/Paris").
   *
   * @since 1.0.0
   */
  public string $timezone;

  /**
   * Display locale (e.g. "fr-FR").
   *
   * @since 1.0.0
   */
  public string $locale;

  /**
   * Date format pattern (e.g. "dd/MM/yyyy").
   *
   * @since 1.0.0
   */
  public string $dateFormat;

  /**
   * First day of the week ("monday" or "sunday").
   *
   * @since 1.0.0
   */
  public string $firstDayOfWeek;

  /**
   * Measurement system ("metric" or "imperial").
   *
   * @since 1.0.0
   */
  public string $measurementSystem;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationRegionalSettings class.
   *
   * @since 1.0.0
   *
   * @param string $timezone the IANA timezone identifier
   * @param string $locale the display locale
   * @param string $dateFormat the date format pattern
   * @param string $firstDayOfWeek the first day of the week
   * @param string $measurementSystem the measurement system
   */
  public function __construct(
    string $timezone = self::DEFAULT_TIMEZONE,
    string $locale = self::DEFAULT_LOCALE,
    string $dateFormat = self::DEFAULT_DATE_FORMAT,
    string $firstDayOfWeek = self::DEFAULT_FIRST_DAY,
    string $measurementSystem = self::DEFAULT_MEASUREMENT_SYSTEM,
  ) {
    $this->timezone = trim($timezone);
    $this->locale = $locale;
    $this->dateFormat = $dateFormat;
    $this->firstDayOfWeek = $firstDayOfWeek;
    $this->measurementSystem = $measurementSystem;

    $this->validate();
  }
  // #endregion

  // #region Methods
  /**
   * Method toArray.
   *
   * Returns the regional settings as a serializable array.
   *
   * @since 1.0.0
   *
   * @return array<string, string> the settings array
   */
  public function toArray(): array
  {
    return [
      'timezone' => $this->timezone,
      'locale' => $this->locale,
      'date_format' => $this->dateFormat,
      'first_day_of_week' => $this->firstDayOfWeek,
      'measurement_system' => $this->measurementSystem,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates regional settings from a (possibly partial) array, falling back to
   * the defaults for any missing value.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    return new self(
      timezone: self::stringOr($data['timezone'] ?? null, self::DEFAULT_TIMEZONE),
      locale: self::stringOr($data['locale'] ?? null, self::DEFAULT_LOCALE),
      dateFormat: self::stringOr($data['date_format'] ?? null, self::DEFAULT_DATE_FORMAT),
      firstDayOfWeek: self::stringOr($data['first_day_of_week'] ?? null, self::DEFAULT_FIRST_DAY),
      measurementSystem: self::stringOr($data['measurement_system'] ?? null, self::DEFAULT_MEASUREMENT_SYSTEM),
    );
  }

  /**
   * Ensures every value is within its supported catalog.
   */
  private function validate(): void
  {
    if ('' === $this->timezone || !in_array($this->timezone, timezone_identifiers_list(), true)) {
      throw InvalidValueException::because(sprintf('Unsupported timezone "%s".', $this->timezone));
    }

    if (!in_array($this->locale, self::ALLOWED_LOCALES, true)) {
      throw InvalidValueException::because(sprintf('Unsupported locale "%s".', $this->locale));
    }

    if (!in_array($this->dateFormat, self::ALLOWED_DATE_FORMATS, true)) {
      throw InvalidValueException::because(sprintf('Unsupported date format "%s".', $this->dateFormat));
    }

    if (!in_array($this->firstDayOfWeek, self::ALLOWED_FIRST_DAYS, true)) {
      throw InvalidValueException::because(sprintf('Unsupported first day of week "%s".', $this->firstDayOfWeek));
    }

    if (!in_array($this->measurementSystem, self::ALLOWED_MEASUREMENT_SYSTEMS, true)) {
      throw InvalidValueException::because(sprintf('Unsupported measurement system "%s".', $this->measurementSystem));
    }
  }

  /**
   * @param mixed $value the candidate value
   * @param string $fallback the fallback used for non-string values
   *
   * @return string the resolved string
   */
  private static function stringOr(mixed $value, string $fallback): string
  {
    return is_string($value) && '' !== trim($value) ? $value : $fallback;
  }
  // #endregion
}
