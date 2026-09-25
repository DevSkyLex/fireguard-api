<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Support;

use function array_keys;
use function array_shift;
use function explode;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function str_contains;
use function ucfirst;

/** Normalizes dashboard query data into the public API output shape. */
final class OrganizationDashboardOutputNormalizer
{
  // region Constants
  /**
   * @var list<array{source: string, metric: string, key: string, label: string}>
   */
  private const array COMPARISON_CARD_DEFINITIONS = [
    [
      'source' => 'inspectionsPerformed',
      'metric' => 'inspections_performed',
      'key' => 'inspections',
      'label' => 'Inspections',
    ],
    [
      'source' => 'facilitiesCreated',
      'metric' => 'facilities_created',
      'key' => 'facilities',
      'label' => 'Facilities',
    ],
    [
      'source' => 'membersJoined',
      'metric' => 'members_joined',
      'key' => 'members',
      'label' => 'Members',
    ],
    [
      'source' => 'equipmentCreated',
      'metric' => 'equipment_created',
      'key' => 'equipment',
      'label' => 'Equipment',
    ],
    [
      'source' => 'nonConformitiesOpened',
      'metric' => 'non_conformities_opened',
      'key' => 'nonConformitiesOpened',
      'label' => 'Non-Conformities Opened',
    ],
    [
      'source' => 'nonConformitiesResolved',
      'metric' => 'non_conformities_resolved',
      'key' => 'nonConformitiesResolved',
      'label' => 'Non-Conformities Resolved',
    ],
  ];

  /**
   * @var array<string, string>
   */
  private const array OVERVIEW_PRIMARY_KEYS = [
    'members' => 'total',
    'roles' => 'total',
    'invitations' => 'pending',
    'facilities' => 'total',
    'equipment' => 'operational',
    'inspections' => 'closed',
    'nonConformities' => 'open',
    // Open, not total: the headline figure is the work still in flight, the
    // same reading `nonConformities` takes.
    'interventions' => 'open',
  ];

  private const string HEALTH_UNIT_PERCENT = 'percent';

  private const float HEALTH_PERCENT_MAX = 100.0;
  // endregion

  /**
   * @param array<string, mixed> $overview
   * @param array<string, string> $primaryMetricKeys
   *
   * @return array<string, array{summary: list<array{key: string, value: int}>, primary: ?array{key: string, value: int}}>
   */
  public static function normalizeOverview(array $overview, array $primaryMetricKeys = []): array
  {
    $normalized = [];
    foreach ($overview as $widgetKey => $widgetData) {
      if (!is_array($widgetData)) {
        continue;
      }

      $summary = [];
      foreach ($widgetData as $entryKey => $entryValue) {
        if (!is_string($entryKey)) {
          continue;
        }

        if (is_int($entryValue)) {
          $summary[] = [
            'key' => $entryKey,
            'value' => $entryValue,
          ];
        }
      }

      $normalized[$widgetKey] = [
        'summary' => $summary,
        'primary' => self::resolveOverviewPrimaryMetric($widgetKey, $summary, $primaryMetricKeys[$widgetKey] ?? null),
      ];
    }

    return $normalized;
  }

  /**
   * @param array<string, float> $health
   *
   * @return array{metrics: list<array{key: string, value: float, unit: string, max: ?float}>}
   */
  public static function normalizeHealth(array $health): array
  {
    $metrics = [];
    foreach ($health as $metricKey => $value) {
      $metrics[] = [
        'key' => $metricKey,
        'value' => $value,
        'unit' => self::HEALTH_UNIT_PERCENT,
        'max' => self::HEALTH_PERCENT_MAX,
      ];
    }

    return ['metrics' => $metrics];
  }

  /**
   * @param list<array{code: string, severity: string, count: int}> $alerts
   *
   * @return list<array{code: string, severity: string, count: int}>
   */
  public static function normalizeAlerts(array $alerts): array
  {
    return $alerts;
  }

  /**
   * @param array<string, mixed> $comparison
   *
   * @return array{
   *   mode: string,
   *   from: ?string,
   *   to: ?string,
   *   metrics: list<array{key: string, metric: string, label: string, value: ?string, current: ?int, previous: ?int, delta: ?float, direction: ?string}>,
   *   health: array{metrics: list<array{key: string, unit: string, max: ?float, current: ?float, previous: ?float, delta: ?float, direction: ?string}>}
   * }
   */
  public static function normalizeComparison(array $comparison): array
  {
    $mode = is_string($comparison['mode'] ?? null) ? $comparison['mode'] : 'none';
    $from = is_string($comparison['from'] ?? null) ? $comparison['from'] : null;
    $to = is_string($comparison['to'] ?? null) ? $comparison['to'] : null;
    $current = self::normalizeMixedMap($comparison['current'] ?? []);
    $previous = self::normalizeMixedMap($comparison['previous'] ?? []);
    $deltas = self::normalizeMixedMap($comparison['deltas'] ?? []);
    $health = is_array($comparison['health'] ?? null) ? $comparison['health'] : [];
    $healthCurrent = self::normalizeScalarMap($health['current'] ?? []);
    $healthPrevious = self::normalizeScalarMap($health['previous'] ?? []);
    $healthDeltas = self::normalizeScalarMap($health['deltas'] ?? []);

    return [
      'mode' => $mode,
      'from' => $from,
      'to' => $to,
      'metrics' => 'previous_period' === $mode ? self::buildComparisonMetrics($current, $previous, $deltas) : [],
      'health' => [
        'metrics' => 'previous_period' === $mode
          ? self::buildHealthComparisonMetrics($healthCurrent, $healthPrevious, $healthDeltas)
          : [],
      ],
    ];
  }

  /**
   * @return array<string, string>
   */
  public static function resolveOverviewPrimaryMetricKeys(?string $equipmentStatus, ?string $inspectionStatus, ?string $nonConformityStatus): array
  {
    $keys = [];

    if (null !== $equipmentStatus) {
      $keys['equipment'] = self::camelizeOverviewMetricKey($equipmentStatus);
    }

    if (null !== $inspectionStatus) {
      $keys['inspections'] = self::camelizeOverviewMetricKey($inspectionStatus);
    }

    if (null !== $nonConformityStatus) {
      $keys['nonConformities'] = self::camelizeOverviewMetricKey($nonConformityStatus);
    }

    return $keys;
  }

  /**
   * Method normalizeTrends.
   *
   * Passes the per-KPI running-total sparkline series through unchanged
   * (the handler already produces the exact shape); defensive defaults
   * keep the output stable if a series is ever missing.
   *
   * @since 1.0.0
   *
   * @param array{facilities?: list<array{bucket: string, value: int}>, members?: list<array{bucket: string, value: int}>, equipment?: list<array{bucket: string, value: int}>, inspections?: list<array{bucket: string, value: int}>} $trends
   *
   * @return array{facilities: list<array{bucket: string, value: int}>, members: list<array{bucket: string, value: int}>, equipment: list<array{bucket: string, value: int}>, inspections: list<array{bucket: string, value: int}>}
   */
  public static function normalizeTrends(array $trends): array
  {
    return [
      'facilities' => $trends['facilities'] ?? [],
      'members' => $trends['members'] ?? [],
      'equipment' => $trends['equipment'] ?? [],
      'inspections' => $trends['inspections'] ?? [],
    ];
  }

  /**
   * @param array<string, mixed> $current
   * @param array<string, mixed> $previous
   * @param array<string, mixed> $deltas
   *
   * @return list<array{key: string, metric: string, label: string, value: ?string, current: ?int, previous: ?int, delta: ?float, direction: ?string}>
   */
  private static function buildComparisonMetrics(array $current, array $previous, array $deltas): array
  {
    $metrics = [];
    foreach (self::COMPARISON_CARD_DEFINITIONS as $definition) {
      $sourceKey = $definition['source'];
      $metricKey = $definition['metric'];
      $currentValue = self::extractMetricIntValue($current, $sourceKey, $metricKey);
      $previousValue = self::extractMetricIntValue($previous, $sourceKey, $metricKey);
      $delta = self::extractMetricFloatValue($deltas, $sourceKey, $metricKey);
      $difference = null !== $currentValue && null !== $previousValue ? $currentValue - $previousValue : null;

      $metrics[] = [
        'key' => $definition['key'],
        'metric' => $metricKey,
        'label' => $definition['label'],
        'value' => self::formatSignedMetricDifference($difference),
        'current' => $currentValue,
        'previous' => $previousValue,
        'delta' => $delta,
        'direction' => self::resolveDirection(null !== $difference ? (float) $difference : $delta),
      ];
    }

    return $metrics;
  }

  /**
   * @param array<string, float> $current
   * @param array<string, float> $previous
   * @param array<string, float> $deltas
   *
   * @return list<array{key: string, unit: string, max: ?float, current: ?float, previous: ?float, delta: ?float, direction: ?string}>
   */
  private static function buildHealthComparisonMetrics(array $current, array $previous, array $deltas): array
  {
    $keys = [];
    foreach ([$current, $previous, $deltas] as $source) {
      foreach (array_keys($source) as $key) {
        $keys[$key] = true;
      }
    }

    $metrics = [];
    foreach (array_keys($keys) as $key) {
      $delta = $deltas[$key] ?? null;

      $metrics[] = [
        'key' => $key,
        'unit' => self::HEALTH_UNIT_PERCENT,
        'max' => self::HEALTH_PERCENT_MAX,
        'current' => $current[$key] ?? null,
        'previous' => $previous[$key] ?? null,
        'delta' => $delta,
        'direction' => self::resolveDirection($delta),
      ];
    }

    return $metrics;
  }

  /**
   * @param list<array{key: string, value: int}> $summary
   *
   * @return ?array{key: string, value: int}
   */
  private static function resolveOverviewPrimaryMetric(string $widgetKey, array $summary, ?string $overrideKey = null): ?array
  {
    $preferredKey = $overrideKey ?? self::OVERVIEW_PRIMARY_KEYS[$widgetKey] ?? null;

    if (null !== $preferredKey) {
      foreach ($summary as $entry) {
        if ($entry['key'] === $preferredKey) {
          return $entry;
        }
      }
    }

    return $summary[0] ?? null;
  }

  private static function camelizeOverviewMetricKey(string $value): string
  {
    if (!str_contains($value, '_')) {
      return $value;
    }

    $segments = explode('_', $value);
    $normalized = (string) array_shift($segments);

    foreach ($segments as $segment) {
      $normalized .= ucfirst($segment);
    }

    return $normalized;
  }

  private static function resolveDirection(?float $delta): ?string
  {
    return match (true) {
      null === $delta => null,
      $delta > 0.0 => 'up',
      $delta < 0.0 => 'down',
      default => 'stable',
    };
  }

  private static function formatSignedMetricDifference(?int $difference): ?string
  {
    if (null === $difference) {
      return null;
    }

    if (0 === $difference) {
      return '0';
    }

    return sprintf('%+d', $difference);
  }

  /**
   * Method extractMetricIntValue.
   *
   * Extracts an integer metric value from the provided values array by checking
   * both the legacy and current metric keys.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $values the array of metric values to search, expected to
   *                                     be an associative array where keys are metric identifiers and values are their corresponding mixed values
   * @param string $legacyKey The legacy metric key to check for the integer value (e.g., "totalEinterventions").
   * @param string $metricKey The current metric key to check for the integer value (e.g., "emissionsTotal").
   *
   * @return int|null the extracted integer value if found under either the legacy or current
   *                  metric key, or null if not found or if the value is not an integer
   */
  private static function extractMetricIntValue(array $values, string $legacyKey, string $metricKey): ?int
  {
    foreach ([$metricKey, $legacyKey] as $candidate) {
      if (isset($values[$candidate]) && is_int($values[$candidate])) {
        return $values[$candidate];
      }
    }

    return null;
  }

  /**
   * Method extractMetricFloatValue.
   *
   * Extracts a float metric value from the provided values array by checking
   * both the legacy and current metric keys.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $values the array of metric values to search, expected to
   *                                     be an associative array where keys are metric identifiers and values are their corresponding mixed values
   * @param string $legacyKey The legacy metric key to check for the float value (e.g., "emissionsDeltaPercent").
   * @param string $metricKey The current metric key to check for the float value (
   *                          e.g., "emissionsDeltaPercentage").
   *
   * @return float|null The extracted float value if found under either the legacy or current
   */
  private static function extractMetricFloatValue(array $values, string $legacyKey, string $metricKey): ?float
  {
    foreach ([$metricKey, $legacyKey] as $candidate) {
      if (isset($values[$candidate]) && (is_float($values[$candidate]) || is_int($values[$candidate]))) {
        return (float) $values[$candidate];
      }
    }

    return null;
  }

  /**
   * Method normalizeMixedMap.
   *
   * Normalizes a mixed input into an associative array with
   * string keys and mixed values.
   *
   * @since 1.0.0
   *
   * @param mixed $values the mixed input to normalize, expected
   *                      to be an array of key-value pairs where keys are strings
   *
   * @return array<string, mixed> An associative array with string
   *                              keys and mixed values, where non-string keys are ignored.
   *                              If the input is not an array, an empty array is returned.
   */
  private static function normalizeMixedMap(mixed $values): array
  {
    if (!is_array($values)) {
      return [];
    }

    $normalized = [];
    foreach ($values as $key => $value) {
      if (!is_string($key)) {
        continue;
      }

      $normalized[$key] = $value;
    }

    return $normalized;
  }

  /**
   * Method normalizeScalarMap.
   *
   * Normalizes a mixed input into an associative array with string keys and float values.
   *
   * @since 1.0.0
   *
   * @param mixed $values the mixed input to normalize, expected to be an array
   *                      of key-value pairs where keys are strings and values are numeric (int or float)
   *
   * @return array<string, float> An associative array with string keys and float
   *                              values, where non-string keys or non-numeric values are ignored. If the input
   *                              is not an array, an empty array is returned.
   */
  private static function normalizeScalarMap(mixed $values): array
  {
    if (!is_array($values)) {
      return [];
    }

    $normalized = [];
    foreach ($values as $key => $value) {
      if (!is_string($key) || (!is_float($value) && !is_int($value))) {
        continue;
      }

      $normalized[$key] = (float) $value;
    }

    return $normalized;
  }
}
