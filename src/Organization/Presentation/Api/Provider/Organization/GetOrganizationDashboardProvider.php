<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use DateTimeZone;
use Equipment\Domain\ValueObject\{EquipmentStatus, EquipmentType};
use Exception;
use Facility\Domain\ValueObject\FacilityType;
use Inspection\Domain\ValueObject\{InspectionResult, InspectionStatus, InspectorType, NonConformitySeverity, NonConformityStatus};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Support\DashboardDateTimeParser;
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboard\{GetOrganizationDashboardQuery, GetOrganizationDashboardResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardOutput;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_column;
use function array_keys;
use function array_shift;
use function explode;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function str_contains;
use function timezone_identifiers_list;
use function trim;
use function ucfirst;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Provider GetOrganizationDashboardProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @implements ProviderInterface<OrganizationDashboardOutput>
 */
final readonly class GetOrganizationDashboardProvider implements ProviderInterface
{
  // #region Traits
  /**
   * Trait UnwrapsOrganizationQueryExceptions.
   *
   * This trait provides utility methods to unwrap exceptions thrown by the query bus
   * and identify specific domain exceptions related to organization queries.
   *
   * It allows the provider to catch generic messenger exceptions and rethrow more specific HTTP exceptions
   * based on the underlying cause, improving error handling and client feedback.
   *
   * @see UnwrapsOrganizationQueryExceptions
   */
  use UnwrapsOrganizationQueryExceptions;
  // #endregion

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
  ];

  private const string HEALTH_UNIT_PERCENT = 'percent';

  private const float HEALTH_PERCENT_MAX = 100.0;
  // endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the provider with required dependencies.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus for dispatching dashboard queries
   * @param OrganizationAuthorizationPort $authorization the authorization service for permission checks
   * @param Security $security the security service for accessing the current user
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method provide.
   *
   * Builds the aggregate dashboard query from API Platform
   * route variables and query-string filters.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $uriVariables API Platform route variables, expected to include 'organizationId'
   * @param array<string, mixed> $context API Platform context, expected to include 'filters' with dashboard filter values
   *
   * @throws AccessDeniedHttpException when the user is not authenticated or misses a required permission
   * @throws BadRequestHttpException when one of the dashboard filters is invalid
   * @throws NotFoundHttpException when the target organization does not exist
   *
   * @return OrganizationDashboardOutput|null OrganizationDashboardOutput on success, null on invalid input
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationDashboardOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    $this->assertDashboardPermissions($user->getId(), $organizationId);

    $filters = $this->normalizeFilters($context['filters'] ?? []);

    $periodFrom = $this->extractDateFilter($filters, 'from');
    $periodTo = $this->extractDateFilter($filters, 'to');
    $requestedTimeZone = $this->extractTimeZoneFilter($filters);
    $facilityType = $this->extractOptionalEnumFilter($filters, 'facilityType', FacilityType::values());
    $equipmentType = $this->extractOptionalEnumFilter($filters, 'equipmentType', EquipmentType::values());
    $equipmentStatus = $this->extractOptionalEnumFilter($filters, 'equipmentStatus', array_column(EquipmentStatus::cases(), 'value'));
    $inspectionStatus = $this->extractOptionalEnumFilter($filters, 'inspectionStatus', InspectionStatus::values());
    $inspectionResult = $this->extractOptionalEnumFilter($filters, 'inspectionResult', InspectionResult::values());
    $inspectorType = $this->extractOptionalEnumFilter($filters, 'inspectorType', InspectorType::values());
    $nonConformityStatus = $this->extractOptionalEnumFilter($filters, 'nonConformityStatus', NonConformityStatus::values());
    $nonConformitySeverity = $this->extractOptionalEnumFilter($filters, 'nonConformitySeverity', NonConformitySeverity::values());
    $overviewPrimaryMetricKeys = $this->resolveOverviewPrimaryMetricKeys(
      equipmentStatus: $equipmentStatus,
      inspectionStatus: $inspectionStatus,
      nonConformityStatus: $nonConformityStatus,
    );

    try {
      /** @var GetOrganizationDashboardResult $result */
      $result = $this->queryBus->ask(new GetOrganizationDashboardQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        periodFrom: null !== $periodFrom ? $this->formatIso8601($periodFrom) : null,
        periodTo: null !== $periodTo ? $this->formatIso8601($periodTo) : null,
        compareWithPreviousPeriod: $this->extractBooleanFilter($filters, 'compare', true),
        timeZone: $requestedTimeZone?->getName(),
        facilityType: $facilityType,
        equipmentType: $equipmentType,
        equipmentStatus: $equipmentStatus,
        inspectionStatus: $inspectionStatus,
        inspectionResult: $inspectionResult,
        inspectorType: $inspectorType,
        nonConformityStatus: $nonConformityStatus,
        nonConformitySeverity: $nonConformitySeverity,
      ));
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    } catch (OrganizationNotFoundException $exception) {
      throw new NotFoundHttpException($exception->getMessage(), $exception);
    } catch (MessengerRuntimeException $exception) {
      $invalidArgument = $this->findWrappedException($exception, InvalidArgumentException::class);
      if ($invalidArgument instanceof InvalidArgumentException) {
        throw new BadRequestHttpException($invalidArgument->getMessage(), $exception);
      }

      $accessDenied = $this->findWrappedException($exception, OrganizationAccessDeniedException::class);
      if ($accessDenied instanceof OrganizationAccessDeniedException) {
        throw new AccessDeniedHttpException($accessDenied->getMessage(), $exception);
      }

      $notFound = $this->findWrappedException($exception, OrganizationNotFoundException::class);
      if ($notFound instanceof OrganizationNotFoundException) {
        throw new NotFoundHttpException($notFound->getMessage(), $exception);
      }

      throw $exception;
    }

    $normalizedOverview = $this->normalizeOverview($result->overview, $overviewPrimaryMetricKeys);
    $normalizedHealth = $this->normalizeHealth($result->health);
    $normalizedAlerts = $this->normalizeAlerts($result->alerts);
    $normalizedComparison = $this->normalizeComparison($result->comparison);

    $output = new OrganizationDashboardOutput();
    $output->generatedAt = $result->generatedAt;
    $output->period = $result->period;
    $output->overview = $normalizedOverview;
    $output->health = $normalizedHealth;
    $output->alerts = $normalizedAlerts;
    $output->comparison = $normalizedComparison;

    return $output;
  }

  /**
   * Method assertDashboardPermissions.
   *
   * Ensures the caller owns every permission required
   * by the aggregate dashboard payload.
   *
   * @since 1.0.0
   *
   * @param string $userId the ID of the user making the request
   * @param string $organizationId the ID of the organization for which the dashboard is requested
   *
   * @return void Returns nothing. Throws an exception if the user lacks any required permission.
   */
  private function assertDashboardPermissions(string $userId, string $organizationId): void
  {
    try {
      $this->authorization->assertGrantedPermissions(
        $userId,
        $organizationId,
        OrganizationPermissionCatalog::dashboardReadDependencies(),
      );
    } catch (OrganizationAccessDeniedException $exception) {
      throw new AccessDeniedHttpException($exception->getMessage(), $exception);
    }
  }

  /**
   * Method normalizeFilters.
   *
   * Normalizes the raw filters from the API Platform context to ensure
   * they are in a consistent format for processing.
   *
   * @since 1.0.0
   *
   * @param mixed $filters the raw filters from the API Platform context, expected to be an array of key-value pairs
   *
   * @return array<string, mixed> A normalized associative array of filters with string keys.
   *                              Non-string keys are ignored, and non-array input results in an empty array.
   */
  private function normalizeFilters(mixed $filters): array
  {
    if (!is_array($filters)) {
      return [];
    }

    $normalized = [];
    foreach ($filters as $key => $value) {
      if (is_string($key)) {
        $normalized[$key] = $value;
      }
    }

    return $normalized;
  }

  /**
   * Method extractDateFilter.
   *
   * Extracts and validates a date filter from
   * the normalized filters array.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the normalized filters array
   * @param string $name The name of the date filter to extract (e.g., 'from' or 'to').
   *
   * @throws BadRequestHttpException if the filter value is present but invalid (e.g., not a valid date string).
   *
   * @return DateTimeImmutable|null the parsed DateTimeImmutable object if the filter is valid,
   *                                or null if the filter is not set or empty
   */
  private function extractDateFilter(array $filters, string $name): ?DateTimeImmutable
  {
    $value = $filters[$name] ?? null;
    if (!is_string($value) || '' === $value) {
      return null;
    }

    try {
      return DashboardDateTimeParser::parse($value, $name);
    } catch (InvalidArgumentException $exception) {
      throw new BadRequestHttpException($exception->getMessage(), $exception);
    }
  }

  /**
   * @param array<string, mixed> $filters
   */
  private function extractBooleanFilter(array $filters, string $name, bool $default): bool
  {
    $value = $filters[$name] ?? null;
    if (null === $value) {
      return $default;
    }
    if (is_bool($value)) {
      return $value;
    }
    if (!is_string($value)) {
      throw new BadRequestHttpException(sprintf(
        'Invalid "%s" filter. Allowed values: true, false, 1, 0, yes, no, on, off.',
        $name,
      ));
    }

    $value = trim($value);
    if ('' === $value) {
      throw new BadRequestHttpException(sprintf(
        'Invalid "%s" filter. Allowed values: true, false, 1, 0, yes, no, on, off.',
        $name,
      ));
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (null !== $parsed) {
      return $parsed;
    }

    throw new BadRequestHttpException(sprintf(
      'Invalid "%s" filter. Allowed values: true, false, 1, 0, yes, no, on, off.',
      $name,
    ));
  }

  /**
   * @param array<string, mixed> $filters
   */
  private function extractTimeZoneFilter(array $filters): ?DateTimeZone
  {
    $value = $filters['timezone'] ?? null;
    if (!is_string($value) || '' === $value) {
      return null;
    }

    if (!in_array($value, timezone_identifiers_list(), true)) {
      throw new BadRequestHttpException('Invalid "timezone" filter. Use a valid IANA timezone such as Europe/Paris.');
    }

    try {
      return new DateTimeZone($value);
    } catch (Exception) {
      throw new BadRequestHttpException('Invalid "timezone" filter. Use a valid IANA timezone such as Europe/Paris.');
    }
  }

  /**
   * @param array<string, mixed> $filters the normalized filters array
   * @param list<string> $allowedValues the list of allowed string values for the filter
   *
   * @throws BadRequestHttpException if the provided filter value is not in the list of allowed values
   *
   * @return string|null the validated filter value if present and valid, or null if not set or empty
   */
  private function extractOptionalEnumFilter(array $filters, string $name, array $allowedValues): ?string
  {
    $value = $filters[$name] ?? null;
    if (!is_string($value) || '' === $value) {
      return null;
    }

    if (!in_array($value, $allowedValues, true)) {
      throw new BadRequestHttpException(sprintf(
        'Invalid "%s" filter. Allowed values: %s.',
        $name,
        implode(', ', $allowedValues),
      ));
    }

    return $value;
  }

  /**
   * @param array<string, mixed> $overview
   * @param array<string, string> $primaryMetricKeys
   *
   * @return array<string, array{summary: list<array{key: string, value: int}>, primary: ?array{key: string, value: int}}>
   */
  private function normalizeOverview(array $overview, array $primaryMetricKeys = []): array
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

          continue;
        }
      }

      $normalized[$widgetKey] = [
        'summary' => $summary,
        'primary' => $this->resolveOverviewPrimaryMetric($widgetKey, $summary, $primaryMetricKeys[$widgetKey] ?? null),
      ];
    }

    return $normalized;
  }

  /**
   * @param array<string, float> $health
   *
   * @return array{metrics: list<array{key: string, value: float, unit: string, max: ?float}>}
   */
  private function normalizeHealth(array $health): array
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
  private function normalizeAlerts(array $alerts): array
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
  private function normalizeComparison(array $comparison): array
  {
    $mode = is_string($comparison['mode'] ?? null) ? $comparison['mode'] : 'none';
    $from = is_string($comparison['from'] ?? null) ? $comparison['from'] : null;
    $to = is_string($comparison['to'] ?? null) ? $comparison['to'] : null;
    $current = $this->normalizeMixedMap($comparison['current'] ?? []);
    $previous = $this->normalizeMixedMap($comparison['previous'] ?? []);
    $deltas = $this->normalizeMixedMap($comparison['deltas'] ?? []);
    $health = is_array($comparison['health'] ?? null) ? $comparison['health'] : [];
    $healthCurrent = $this->normalizeScalarMap($health['current'] ?? []);
    $healthPrevious = $this->normalizeScalarMap($health['previous'] ?? []);
    $healthDeltas = $this->normalizeScalarMap($health['deltas'] ?? []);

    return [
      'mode' => $mode,
      'from' => $from,
      'to' => $to,
      'metrics' => 'previous_period' === $mode ? $this->buildComparisonMetrics($current, $previous, $deltas) : [],
      'health' => [
        'metrics' => 'previous_period' === $mode
          ? $this->buildHealthComparisonMetrics($healthCurrent, $healthPrevious, $healthDeltas)
          : [],
      ],
    ];
  }

  /**
   * @param array<string, mixed> $current
   * @param array<string, mixed> $previous
   * @param array<string, mixed> $deltas
   *
   * @return list<array{key: string, metric: string, label: string, value: ?string, current: ?int, previous: ?int, delta: ?float, direction: ?string}>
   */
  private function buildComparisonMetrics(array $current, array $previous, array $deltas): array
  {
    $metrics = [];
    foreach (self::COMPARISON_CARD_DEFINITIONS as $definition) {
      $sourceKey = $definition['source'];
      $metricKey = $definition['metric'];
      $currentValue = $this->extractMetricIntValue($current, $sourceKey, $metricKey);
      $previousValue = $this->extractMetricIntValue($previous, $sourceKey, $metricKey);
      $delta = $this->extractMetricFloatValue($deltas, $sourceKey, $metricKey);
      $difference = null !== $currentValue && null !== $previousValue ? $currentValue - $previousValue : null;

      $metrics[] = [
        'key' => $definition['key'],
        'metric' => $metricKey,
        'label' => $definition['label'],
        'value' => $this->formatSignedMetricDifference($difference),
        'current' => $currentValue,
        'previous' => $previousValue,
        'delta' => $delta,
        'direction' => $this->resolveDirection(null !== $difference ? (float) $difference : $delta),
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
  private function buildHealthComparisonMetrics(array $current, array $previous, array $deltas): array
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
        'direction' => $this->resolveDirection($delta),
      ];
    }

    return $metrics;
  }

  /**
   * @param list<array{key: string, value: int}> $summary
   *
   * @return ?array{key: string, value: int}
   */
  private function resolveOverviewPrimaryMetric(string $widgetKey, array $summary, ?string $overrideKey = null): ?array
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

  /**
   * @return array<string, string>
   */
  private function resolveOverviewPrimaryMetricKeys(?string $equipmentStatus, ?string $inspectionStatus, ?string $nonConformityStatus): array
  {
    $keys = [];

    if (null !== $equipmentStatus) {
      $keys['equipment'] = $this->camelizeOverviewMetricKey($equipmentStatus);
    }

    if (null !== $inspectionStatus) {
      $keys['inspections'] = $this->camelizeOverviewMetricKey($inspectionStatus);
    }

    if (null !== $nonConformityStatus) {
      $keys['nonConformities'] = $this->camelizeOverviewMetricKey($nonConformityStatus);
    }

    return $keys;
  }

  private function camelizeOverviewMetricKey(string $value): string
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

  private function resolveDirection(?float $delta): ?string
  {
    if (null === $delta) {
      return null;
    }

    if ($delta > 0.0) {
      return 'up';
    }

    if ($delta < 0.0) {
      return 'down';
    }

    return 'stable';
  }

  private function formatSignedMetricDifference(?int $difference): ?string
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
   * @param string $legacyKey The legacy metric key to check for the integer value (e.g., "totalEmissions").
   * @param string $metricKey The current metric key to check for the integer value (e.g., "emissionsTotal").
   *
   * @return int|null the extracted integer value if found under either the legacy or current
   *                  metric key, or null if not found or if the value is not an integer
   */
  private function extractMetricIntValue(array $values, string $legacyKey, string $metricKey): ?int
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
  private function extractMetricFloatValue(array $values, string $legacyKey, string $metricKey): ?float
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
  private function normalizeMixedMap(mixed $values): array
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
  private function normalizeScalarMap(mixed $values): array
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

  /**
   * Method formatIso8601.
   *
   * Formats a DateTimeImmutable object into an ISO 8601 string, including
   * microseconds only if they are non-zero.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $value the DateTimeImmutable object to format as an ISO 8601 string
   *
   * @return string the ISO 8601 formatted string representation of the provided DateTimeImmutable
   *                object, including microseconds if they are non-zero, or omitting them if they are zero
   */
  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u')
      ? $value->format('Y-m-d\TH:i:sP')
      : $value->format('Y-m-d\TH:i:s.uP');
  }
  // #endregion
}
