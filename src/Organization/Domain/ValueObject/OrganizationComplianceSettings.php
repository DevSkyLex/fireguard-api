<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

use DateInterval;
use DateTimeImmutable;
use Exception;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Shared\Domain\Exception\InvalidValueException;

use function array_key_exists;
use function array_keys;
use function in_array;
use function is_array;
use function is_int;
use function is_string;
use function ksort;
use function sprintf;

/**
 * ValueObject OrganizationComplianceSettings.
 *
 * Organization-wide compliance policy: non-conformity resolution SLAs per
 * severity, inspection periodicity per equipment type, and the reminder
 * window. Only the values the organization customized are stored; effective
 * values are resolved against {@see OrganizationComplianceDefaults} at read
 * time so catalog updates propagate to non-customized organizations.
 *
 * Equipment-type keys are stored as plain strings and are not validated here
 * (the Organization domain never depends on the Equipment enum); the API layer
 * validates them against the equipment-type catalog port. Unknown keys left by
 * a removed equipment type are tolerated and simply ignored.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationComplianceSettings
{
  // #region Constants
  /**
   * Supported non-conformity severity values.
   *
   * @var list<string>
   */
  public const array ALLOWED_SEVERITIES = ['low', 'medium', 'high', 'critical'];

  /**
   * Inclusive bounds for SLA days.
   */
  public const int MIN_SLA_DAYS = 1;

  public const int MAX_SLA_DAYS = 365;

  /**
   * Inclusive bounds for the reminder window, in days.
   */
  public const int MIN_REMINDER_WINDOW_DAYS = 1;

  public const int MAX_REMINDER_WINDOW_DAYS = 180;
  // #endregion

  // #region Properties
  /**
   * Customized non-conformity SLA days per severity (subset of severities).
   *
   * @since 1.0.0
   *
   * @var array<string, int>
   */
  public array $nonConformitySlaDays;

  /**
   * Customized inspection periodicity (ISO-8601 duration) per equipment type
   * value (subset of equipment types).
   *
   * @since 1.0.0
   *
   * @var array<string, string>
   */
  public array $inspectionPeriodicityDefaults;

  /**
   * Number of days before a due date during which reminders fire.
   *
   * @since 1.0.0
   */
  public int $reminderWindowDays;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationComplianceSettings class.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $nonConformitySlaDays the customized SLA days per severity (validated)
   * @param array<array-key, mixed> $inspectionPeriodicityDefaults the customized periodicities per equipment type (validated)
   * @param int $reminderWindowDays the reminder window in days
   */
  public function __construct(
    array $nonConformitySlaDays = [],
    array $inspectionPeriodicityDefaults = [],
    int $reminderWindowDays = OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS,
  ) {
    $this->nonConformitySlaDays = self::validateSlaDays($nonConformitySlaDays);
    $this->inspectionPeriodicityDefaults = self::validatePeriodicities($inspectionPeriodicityDefaults);
    $this->reminderWindowDays = self::validateReminderWindow($reminderWindowDays);
  }
  // #endregion

  // #region Methods
  /**
   * Method effectiveNonConformitySlaDays.
   *
   * Returns the catalog SLA defaults overlaid with the customized entries.
   *
   * @since 1.0.0
   *
   * @return array<string, int> the effective SLA days per severity
   */
  public function effectiveNonConformitySlaDays(): array
  {
    return [...OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS, ...$this->nonConformitySlaDays];
  }

  /**
   * Method effectiveInspectionPeriodicities.
   *
   * Returns the catalog periodicity defaults overlaid with the customized
   * entries, sorted by equipment type for stable output.
   *
   * @since 1.0.0
   *
   * @return array<string, string> the effective periodicity per equipment type
   */
  public function effectiveInspectionPeriodicities(): array
  {
    $effective = [...OrganizationComplianceDefaults::INSPECTION_PERIODICITIES, ...$this->inspectionPeriodicityDefaults];
    ksort($effective);

    return $effective;
  }

  /**
   * Method slaDaysFor.
   *
   * Returns the effective SLA days for a severity value.
   *
   * @since 1.0.0
   *
   * @param string $severity the severity value
   *
   * @return ?int the SLA days, or null for an unknown severity
   */
  public function slaDaysFor(string $severity): ?int
  {
    return $this->effectiveNonConformitySlaDays()[$severity] ?? null;
  }

  /**
   * Method periodicityFor.
   *
   * Returns the effective inspection periodicity for an equipment type value.
   *
   * @since 1.0.0
   *
   * @param string $equipmentType the equipment type value
   *
   * @return ?string the ISO-8601 periodicity, or null when the type is untracked
   */
  public function periodicityFor(string $equipmentType): ?string
  {
    return $this->inspectionPeriodicityDefaults[$equipmentType]
      ?? OrganizationComplianceDefaults::INSPECTION_PERIODICITIES[$equipmentType]
      ?? null;
  }

  /**
   * Method mergedWith.
   *
   * Applies a partial section payload (snake_case keys) on top of the current
   * settings and returns the resulting instance. Map entries with a `null`
   * value remove the customization (reverting the key to the catalog default);
   * a `null` scalar leaves the current value unchanged.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $partial the partial section payload
   *
   * @return self the merged instance
   */
  public function mergedWith(array $partial): self
  {
    $slaDays = $this->nonConformitySlaDays;
    if (array_key_exists('non_conformity_sla_days', $partial) && null !== $partial['non_conformity_sla_days']) {
      /** @var array<string, mixed> $entries */
      $entries = $partial['non_conformity_sla_days'];
      $slaDays = self::mergeMap($slaDays, $entries);
    }

    $periodicities = $this->inspectionPeriodicityDefaults;
    if (array_key_exists('inspection_periodicity_defaults', $partial) && null !== $partial['inspection_periodicity_defaults']) {
      /** @var array<string, mixed> $entries */
      $entries = $partial['inspection_periodicity_defaults'];
      $periodicities = self::mergeMap($periodicities, $entries);
    }

    $reminderWindow = $this->reminderWindowDays;
    if (isset($partial['reminder_window_days']) && is_int($partial['reminder_window_days'])) {
      $reminderWindow = $partial['reminder_window_days'];
    }

    /** @var array<string, int> $slaDays */
    /** @var array<string, string> $periodicities */
    return new self(
      nonConformitySlaDays: $slaDays,
      inspectionPeriodicityDefaults: $periodicities,
      reminderWindowDays: $reminderWindow,
    );
  }

  /**
   * Method toArray.
   *
   * Returns the customized compliance settings as a serializable array. Only
   * customizations are persisted; effective values are recomputed on read.
   *
   * @since 1.0.0
   *
   * @return array{non_conformity_sla_days: array<string, int>, inspection_periodicity_defaults: array<string, string>, reminder_window_days: int} the settings array
   */
  public function toArray(): array
  {
    return [
      'non_conformity_sla_days' => $this->nonConformitySlaDays,
      'inspection_periodicity_defaults' => $this->inspectionPeriodicityDefaults,
      'reminder_window_days' => $this->reminderWindowDays,
    ];
  }

  /**
   * Method fromArray.
   *
   * @static
   *
   * Creates compliance settings from a (possibly partial) array, falling back
   * to "nothing customized" for any missing key.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $data the settings data
   *
   * @return self the settings instance
   */
  public static function fromArray(array $data): self
  {
    $slaDays = $data['non_conformity_sla_days'] ?? [];
    $periodicities = $data['inspection_periodicity_defaults'] ?? [];
    $reminderWindow = $data['reminder_window_days'] ?? OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS;

    return new self(
      nonConformitySlaDays: is_array($slaDays) ? $slaDays : [],
      inspectionPeriodicityDefaults: is_array($periodicities) ? $periodicities : [],
      reminderWindowDays: is_int($reminderWindow) ? $reminderWindow : OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS,
    );
  }

  /**
   * Method customizedSeverities.
   *
   * Returns the severity values whose SLA has been customized.
   *
   * @since 1.0.0
   *
   * @return list<string> the customized severity values
   */
  public function customizedSeverities(): array
  {
    return array_keys($this->nonConformitySlaDays);
  }

  /**
   * Method customizedEquipmentTypes.
   *
   * Returns the equipment type values whose periodicity has been customized.
   *
   * @since 1.0.0
   *
   * @return list<string> the customized equipment type values
   */
  public function customizedEquipmentTypes(): array
  {
    return array_keys($this->inspectionPeriodicityDefaults);
  }

  /**
   * Method mergeMap.
   *
   * @static
   *
   * Merges partial map entries into the current map: a `null` value removes
   * the key, any other value replaces it.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $current the current map
   * @param array<string, mixed> $entries the partial entries
   *
   * @return array<string, mixed> the merged map
   */
  private static function mergeMap(array $current, array $entries): array
  {
    foreach ($entries as $key => $value) {
      if (null === $value) {
        unset($current[$key]);

        continue;
      }

      $current[$key] = $value;
    }

    return $current;
  }

  /**
   * Method validateSlaDays.
   *
   * @static
   *
   * Validates the customized SLA map: known severities, integer days in bounds.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $slaDays the customized SLA days
   *
   * @return array<string, int> the validated map
   */
  private static function validateSlaDays(array $slaDays): array
  {
    $validated = [];

    foreach ($slaDays as $severity => $days) {
      if (!is_string($severity) || !in_array($severity, self::ALLOWED_SEVERITIES, true)) {
        throw new InvalidValueException(sprintf('Unknown non-conformity severity "%s".', (string) $severity));
      }

      if (!is_int($days) || $days < self::MIN_SLA_DAYS || $days > self::MAX_SLA_DAYS) {
        throw new InvalidValueException(sprintf(
          'Non-conformity SLA for severity "%s" must be an integer between %d and %d days.',
          $severity,
          self::MIN_SLA_DAYS,
          self::MAX_SLA_DAYS,
        ));
      }

      $validated[$severity] = $days;
    }

    return $validated;
  }

  /**
   * Method validatePeriodicities.
   *
   * @static
   *
   * Validates the customized periodicity map: string keys and ISO-8601
   * durations between roughly one month and ten years.
   *
   * @since 1.0.0
   *
   * @param array<array-key, mixed> $periodicities the customized periodicities
   *
   * @return array<string, string> the validated map
   */
  private static function validatePeriodicities(array $periodicities): array
  {
    $validated = [];

    foreach ($periodicities as $equipmentType => $duration) {
      if (!is_string($equipmentType) || '' === $equipmentType) {
        throw new InvalidValueException('Equipment type keys must be non-empty strings.');
      }

      if (!is_string($duration)) {
        throw new InvalidValueException(sprintf('Inspection periodicity for "%s" must be an ISO-8601 duration string.', $equipmentType));
      }

      self::assertPeriodicityInBounds($equipmentType, $duration);
      $validated[$equipmentType] = $duration;
    }

    return $validated;
  }

  /**
   * Method assertPeriodicityInBounds.
   *
   * @static
   *
   * Asserts that an ISO-8601 duration parses and spans between one month and
   * ten years (evaluated against a fixed reference date).
   *
   * @since 1.0.0
   *
   * @param string $equipmentType the equipment type value (for error messages)
   * @param string $duration the ISO-8601 duration
   */
  private static function assertPeriodicityInBounds(string $equipmentType, string $duration): void
  {
    try {
      $interval = new DateInterval($duration);
    } catch (Exception) {
      throw new InvalidValueException(sprintf('Inspection periodicity "%s" for "%s" is not a valid ISO-8601 duration.', $duration, $equipmentType));
    }

    $reference = new DateTimeImmutable('2000-01-01T00:00:00+00:00');
    $target = $reference->add($interval);

    if ($target < $reference->add(new DateInterval('P28D'))) {
      throw new InvalidValueException(sprintf('Inspection periodicity for "%s" must be at least one month.', $equipmentType));
    }

    if ($target > $reference->add(new DateInterval('P10Y'))) {
      throw new InvalidValueException(sprintf('Inspection periodicity for "%s" must not exceed ten years.', $equipmentType));
    }
  }

  /**
   * Method validateReminderWindow.
   *
   * @static
   *
   * Validates the reminder window bounds.
   *
   * @since 1.0.0
   *
   * @param int $reminderWindowDays the reminder window in days
   *
   * @return int the validated reminder window
   */
  private static function validateReminderWindow(int $reminderWindowDays): int
  {
    if ($reminderWindowDays < self::MIN_REMINDER_WINDOW_DAYS || $reminderWindowDays > self::MAX_REMINDER_WINDOW_DAYS) {
      throw new InvalidValueException(sprintf(
        'Reminder window must be between %d and %d days.',
        self::MIN_REMINDER_WINDOW_DAYS,
        self::MAX_REMINDER_WINDOW_DAYS,
      ));
    }

    return $reminderWindowDays;
  }
  // #endregion
}
