<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Provider\Organization;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Auth\Infrastructure\Security\User\SecurityUser;
use DateInterval;
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

use function array_keys;
use function implode;
use function in_array;
use function is_array;
use function is_float;
use function is_int;
use function is_string;
use function lcfirst;
use function sprintf;
use function str_starts_with;
use function strtolower;
use function substr;
use function timezone_identifiers_list;

/**
 * Provider GetOrganizationDashboardProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
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
   * Constant DEFAULT_DASHBOARD_TIME_ZONE.
   *
   * Defines the default timezone used for the dashboard
   * when no explicit timezone is provided and when implicit
   * inference from request bounds is not possible.
   *
   * @since 1.0.0
   *
   * @var string
   */
  private const string DEFAULT_DASHBOARD_TIME_ZONE = 'UTC';

  /**
   * Constant DEFAULT_TREND_PERIOD_DAYS.
   *
   * Defines the default number of days for the dashboard trend metrics
   * period when no explicit "from" date is provided.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int DEFAULT_TREND_PERIOD_DAYS = 30;

  /**
   * Constant MAX_TREND_PERIOD_DAYS.
   *
   * Defines the maximum allowed number of days for the
   * dashboard trend metrics period.
   *
   * @since 1.0.0
   *
   * @var int
   */
  private const int MAX_TREND_PERIOD_DAYS = 366;

  /**
   * @var array<string, string>
   */
  private const array TREND_METRIC_KEYS = [
    'inspectionsPerformed' => 'inspections_performed',
    'nonConformitiesOpened' => 'non_conformities_opened',
    'nonConformitiesResolved' => 'non_conformities_resolved',
  ];
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
    $referenceNow = new DateTimeImmutable('now', new DateTimeZone(self::DEFAULT_DASHBOARD_TIME_ZONE));
    $dashboardTimeZone = $this->resolveDashboardTimeZone(
      requestedTimeZone: $requestedTimeZone,
      periodFrom: $periodFrom,
      periodTo: $periodTo,
      fallbackNow: $referenceNow,
    );
    [$effectivePeriodStart, $effectivePeriodEnd] = $this->resolveRequestedPeriod(
      periodFrom: $periodFrom,
      periodTo: $periodTo,
      dashboardTimeZone: $dashboardTimeZone,
      referenceNow: $referenceNow,
    );
    $this->assertSupportedPeriod($effectivePeriodStart, $effectivePeriodEnd);
    $normalizedPeriodFrom = $periodFrom?->setTimezone($dashboardTimeZone);
    $normalizedPeriodTo = $periodTo?->setTimezone($dashboardTimeZone);

    try {
      /** @var GetOrganizationDashboardResult $result */
      $result = $this->queryBus->ask(new GetOrganizationDashboardQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        periodFrom: null !== $normalizedPeriodFrom ? $this->formatIso8601($normalizedPeriodFrom) : null,
        periodTo: null !== $normalizedPeriodTo ? $this->formatIso8601($normalizedPeriodTo) : null,
        compareWithPreviousPeriod: $this->extractBooleanFilter($filters, 'compare', true),
        granularity: $this->extractGranularityFilter($filters),
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

    $normalizedOverview = $this->normalizeOverview($result->overview);
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
    $output->trendMetrics = $this->buildTrendMetrics($result->trends, $result->comparison);

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
    foreach (OrganizationPermissionCatalog::dashboardReadDependencies() as $permission) {
      if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
        throw new AccessDeniedHttpException(sprintf('Missing %s permission.', $permission));
      }
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
   * Method extractBooleanFilter.
   *
   * Extracts and normalizes a boolean filter from the normalized filters array.
   * Accepts various string representations of boolean values and normalizes them to a boolean type.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the normalized filters array
   * @param string $name The name of the boolean filter to extract (e.g., 'compare').
   * @param bool $default the default boolean value to return if the filter is not set or invalid
   *
   * @return bool The normalized boolean value of the filter, or the default if the filter is not set or invalid.
   *              Recognizes '0', 'false', 'no', 'off' (case-insensitive) as false; all other non-empty strings are true.
   */
  private function extractBooleanFilter(array $filters, string $name, bool $default): bool
  {
    $value = $filters[$name] ?? null;
    if (!is_string($value)) {
      return $default;
    }

    return !in_array(strtolower($value), ['0', 'false', 'no', 'off'], true);
  }

  /**
   * Method extractGranularityFilter.
   *
   * Extracts and validates the 'granularity' filter from the normalized filters array.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the normalized filters array
   *
   * @throws BadRequestHttpException if the provided granularity value is invalid
   *
   * @return string the validated granularity value, defaulting to 'day' if not set or empty
   */
  private function extractGranularityFilter(array $filters): string
  {
    $value = $filters['granularity'] ?? null;
    if (!is_string($value) || '' === $value) {
      return 'day';
    }

    $normalized = strtolower($value);
    if (!in_array($normalized, ['day', 'week', 'month', 'auto'], true)) {
      throw new BadRequestHttpException('Invalid "granularity" filter. Allowed values: day, week, month, auto.');
    }

    return $normalized;
  }

  /**
   * Method extractTimeZoneFilter.
   *
   * Extracts and validates the 'timezone' filter from the
   * normalized filters array.
   *
   * @since 1.0.0
   *
   * @param array<string, mixed> $filters the normalized filters array
   *
   * @throws BadRequestHttpException if the provided timezone value is invalid
   *                                 or not recognized as a valid IANA timezone
   *
   * @return DateTimeZone|null the DateTimeZone object representing the requested timezone, or null if not set
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
   * Method extractOptionalEnumFilter.
   *
   * Extracts and validates an optional enumerated filter
   * from the normalized filters array.
   *
   * @since 1.0.0
   *
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
   * Method resolveDashboardTimeZone.
   *
   * Resolves the dashboard timezone from the explicit
   * filter or from the request bounds.
   *
   * When no explicit timezone is provided, the method accepts only IANA timezone names
   * inferred from the bounds or UTC-compatible offsets.
   *
   * @since 1.0.0
   *
   * @param DateTimeZone|null $requestedTimeZone the explicitly requested timezone from the filters, if any
   * @param DateTimeImmutable|null $periodFrom the parsed "from" date filter, if any
   * @param DateTimeImmutable|null $periodTo the parsed "to" date filter, if any
   * @param DateTimeImmutable $fallbackNow the current datetime to use as a fallback reference for implicit timezone inference
   *
   * @throws BadRequestHttpException if the provided timezone is invalid, or if implicit inference
   *                                 fails due to mixed or non-UTC offsets without an explicit timezone
   *
   * @return DateTimeZone the resolved DateTimeZone object to be used for the dashboard query
   */
  private function resolveDashboardTimeZone(
    ?DateTimeZone $requestedTimeZone,
    ?DateTimeImmutable $periodFrom,
    ?DateTimeImmutable $periodTo,
    DateTimeImmutable $fallbackNow,
  ): DateTimeZone {
    if ($requestedTimeZone instanceof DateTimeZone) {
      return $requestedTimeZone;
    }

    if (
      $periodFrom instanceof DateTimeImmutable
      && $periodTo instanceof DateTimeImmutable
      && $periodFrom->getTimezone()->getName() !== $periodTo->getTimezone()->getName()
    ) {
      throw new BadRequestHttpException('Mixed timezone offsets require the "timezone" filter.');
    }

    return $this->normalizeImplicitDashboardTimeZone(
      $periodFrom?->getTimezone() ?? $periodTo?->getTimezone() ?? $fallbackNow->getTimezone(),
      $periodFrom ?? $periodTo ?? $fallbackNow,
    );
  }

  /**
   * Method resolveRequestedPeriod.
   *
   * Resolves the effective dashboard period start and end datetimes
   * based on the provided filters and dashboard timezone.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable|null $periodFrom the parsed "from" date filter, if any
   * @param DateTimeImmutable|null $periodTo the parsed "to" date filter, if any
   * @param DateTimeZone $dashboardTimeZone the resolved dashboard timezone to apply to the period bounds
   *
   * @return array{0: DateTimeImmutable, 1: DateTimeImmutable} an array containing the effective period start
   *                                                           and end datetimes, both set to the dashboard timezone
   */
  private function resolveRequestedPeriod(
    ?DateTimeImmutable $periodFrom,
    ?DateTimeImmutable $periodTo,
    DateTimeZone $dashboardTimeZone,
    DateTimeImmutable $referenceNow,
  ): array {
    $periodEnd = ($periodTo ?? $referenceNow)->setTimezone($dashboardTimeZone);
    $periodStart = null !== $periodFrom
      ? $periodFrom->setTimezone($dashboardTimeZone)
      : $periodEnd->sub(new DateInterval('P' . (self::DEFAULT_TREND_PERIOD_DAYS - 1) . 'D'))->setTime(0, 0);

    return [$periodStart, $periodEnd];
  }

  /**
   * Method normalizeImplicitDashboardTimeZone.
   *
   * Normalizes an implicitly inferred timezone to an IANA
   * identifier accepted by the dashboard contract.
   *
   * @since 1.0.0
   *
   * @param DateTimeZone $timeZone the implicitly inferred timezone to normalize
   * @param DateTimeImmutable $reference The reference datetime used to determine the offset of the
   *
   * @throws BadRequestHttpException if the inferred timezone is not a valid IANA identifier and cannot be normalized to UTC
   *
   * @return DateTimeZone the normalized DateTimeZone object, guaranteed
   *                      to be a valid IANA timezone or UTC
   */
  private function normalizeImplicitDashboardTimeZone(
    DateTimeZone $timeZone,
    DateTimeImmutable $reference,
  ): DateTimeZone {
    if (in_array($timeZone->getName(), timezone_identifiers_list(), true)) {
      return $timeZone;
    }

    if (0 === $timeZone->getOffset($reference)) {
      return new DateTimeZone('UTC');
    }

    throw new BadRequestHttpException('Non-UTC offset datetimes require the "timezone" filter.');
  }

  /**
   * Method assertSupportedPeriod.
   *
   * Validates dashboard period bounds and the maximum
   * supported window size.
   *
   * @since 1.0.0
   *
   * @param DateTimeImmutable $periodStart the effective start datetime of the requested dashboard period
   * @param DateTimeImmutable $periodEnd the effective end datetime of the requested dashboard period
   *
   * @throws BadRequestHttpException if the period start is after the period end,
   *                                 or if the period length exceeds the maximum allowed days
   *
   * @return void Returns nothing. Throws an exception if the period is
   *              invalid (start after end) or exceeds the maximum allowed length.
   */
  private function assertSupportedPeriod(DateTimeImmutable $periodStart, DateTimeImmutable $periodEnd): void
  {
    if ($periodStart->getTimestamp() > $periodEnd->getTimestamp()) {
      throw new BadRequestHttpException('The "from" datetime filter must be before or equal to "to".');
    }

    $periodLengthInDays = (int) $periodStart->setTime(0, 0)->diff($periodEnd->setTime(0, 0))->days + 1;
    if ($periodLengthInDays > self::MAX_TREND_PERIOD_DAYS) {
      throw new BadRequestHttpException('Dashboard period cannot exceed 366 days.');
    }
  }

  /**
   * @param array<string, mixed> $overview
   *
   * @return array<string, array{summary: list<array{key: string, value: int}>, breakdowns: list<array{key: string, items: list<array{key: string, value: int}>}>}>
   */
  private function normalizeOverview(array $overview): array
  {
    $normalized = [];
    foreach ($overview as $widgetKey => $widgetData) {
      if (!is_array($widgetData)) {
        continue;
      }

      $summary = [];
      $breakdowns = [];
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

        if (is_array($entryValue) && str_starts_with($entryKey, 'by')) {
          $breakdowns[] = [
            'key' => $this->normalizeBreakdownKey($entryKey),
            'items' => $this->normalizeBreakdownItems($entryValue),
          ];
        }
      }

      $normalized[$widgetKey] = [
        'summary' => $summary,
        'breakdowns' => $breakdowns,
      ];
    }

    return $normalized;
  }

  /**
   * @param array<string, float> $health
   *
   * @return array{metrics: list<array{key: string, value: float, unit: string}>}
   */
  private function normalizeHealth(array $health): array
  {
    $metrics = [];
    foreach ($health as $metricKey => $value) {
      $metrics[] = [
        'key' => $metricKey,
        'value' => $value,
        'unit' => 'percent',
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
   *   metrics: list<array{metric: string, current: ?int, previous: ?int, delta: ?float}>,
   *   health: array{metrics: list<array{key: string, unit: string, current: ?float, previous: ?float, delta: ?float}>}
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
   * @param array<string, list<array{bucket: string, value: int}>> $trends
   * @param array<string, mixed> $comparison
   *
   * @return list<array{metric: string, summary: array{total: int, unit: string}, series: list<array{bucket: string, value: int}>, comparison: array{mode: string, current: ?int, previous: ?int, delta: ?float}}>
   */
  private function buildTrendMetrics(array $trends, array $comparison): array
  {
    $metrics = [];
    $mode = is_string($comparison['mode'] ?? null) ? $comparison['mode'] : 'none';
    $current = $this->normalizeMixedMap($comparison['current'] ?? []);
    $previous = $this->normalizeMixedMap($comparison['previous'] ?? []);
    $deltas = $this->normalizeMixedMap($comparison['deltas'] ?? []);

    foreach (self::TREND_METRIC_KEYS as $legacyKey => $metricKey) {
      $series = [];
      if (isset($trends[$metricKey])) {
        $series = $trends[$metricKey];
      } elseif (isset($trends[$legacyKey])) {
        $series = $trends[$legacyKey];
      }

      $metrics[] = [
        'metric' => $metricKey,
        'summary' => [
          'total' => $this->sumTrendSeries($series),
          'unit' => 'count',
        ],
        'series' => $series,
        'comparison' => [
          'mode' => $mode,
          'current' => $this->extractMetricIntValue($current, $legacyKey, $metricKey),
          'previous' => $this->extractMetricIntValue($previous, $legacyKey, $metricKey),
          'delta' => $this->extractMetricFloatValue($deltas, $legacyKey, $metricKey),
        ],
      ];
    }

    return $metrics;
  }

  /**
   * @param array<string, mixed> $current
   * @param array<string, mixed> $previous
   * @param array<string, mixed> $deltas
   *
   * @return list<array{metric: string, current: ?int, previous: ?int, delta: ?float}>
   */
  private function buildComparisonMetrics(array $current, array $previous, array $deltas): array
  {
    $metrics = [];
    foreach (self::TREND_METRIC_KEYS as $legacyKey => $metricKey) {
      $metrics[] = [
        'metric' => $metricKey,
        'current' => $this->extractMetricIntValue($current, $legacyKey, $metricKey),
        'previous' => $this->extractMetricIntValue($previous, $legacyKey, $metricKey),
        'delta' => $this->extractMetricFloatValue($deltas, $legacyKey, $metricKey),
      ];
    }

    return $metrics;
  }

  /**
   * @param array<string, float> $current
   * @param array<string, float> $previous
   * @param array<string, float> $deltas
   *
   * @return list<array{key: string, unit: string, current: ?float, previous: ?float, delta: ?float}>
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
      $metrics[] = [
        'key' => $key,
        'unit' => 'percent',
        'current' => $current[$key] ?? null,
        'previous' => $previous[$key] ?? null,
        'delta' => $deltas[$key] ?? null,
      ];
    }

    return $metrics;
  }

  /**
   * @param array<mixed, mixed> $breakdown
   *
   * @return list<array{key: string, value: int}>
   */
  private function normalizeBreakdownItems(array $breakdown): array
  {
    $items = [];
    foreach ($breakdown as $key => $value) {
      if (!is_string($key) || !is_int($value)) {
        continue;
      }

      $items[] = [
        'key' => $key,
        'value' => $value,
      ];
    }

    return $items;
  }

  /**
   * Method normalizeBreakdownKey.
   *
   * Normalizes a breakdown key from the raw overview data by
   * removing any "by" prefix and converting it to camelCase.
   *
   * @since 1.0.0
   *
   * @param string $key The raw breakdown key from the overview data,
   *                    which may start with "by" (e.g., "byFacilityType").
   *
   * @return string The normalized breakdown key with the "by"
   *                prefix removed and converted to camelCase (e.g., "facilityType").
   */
  private function normalizeBreakdownKey(string $key): string
  {
    return str_starts_with($key, 'by') ? lcfirst(substr($key, 2)) : $key;
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
   * Method sumTrendSeries.
   *
   * Sums the values of a trend series, which is a list of data points each containing a 'value' key with an integer value.
   * This method iterates through the series and accumulates the total sum of the 'value' fields, returning the final total.
   * The input series is expected to be a list of associative arrays, where each array has a 'bucket' key (string) and a 'value' key (integer).
   *
   * @since 1.0.0
   *
   * @param list<array{bucket: string, value: int}> $series
   *
   * @return int The total sum of the 'value' fields in the trend series. If the series is empty, returns 0.
   */
  private function sumTrendSeries(array $series): int
  {
    $total = 0;
    foreach ($series as $point) {
      $total += $point['value'];
    }

    return $total;
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
