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
use Organization\Application\UseCase\Query\Organization\GetOrganizationDashboardTrend\{GetOrganizationDashboardTrendHandler, GetOrganizationDashboardTrendQuery, GetOrganizationDashboardTrendResult};
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\{OrganizationAccessDeniedException, OrganizationNotFoundException};
use Organization\Presentation\Api\Dto\Output\Organization\OrganizationDashboardTrendOutput;
use Organization\Presentation\Api\Operation\OrganizationOperations;
use Organization\Presentation\Api\Support\UnwrapsOrganizationQueryExceptions;
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};

use function array_column;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_float;
use function is_int;
use function is_string;
use function sprintf;
use function strtolower;
use function timezone_identifiers_list;
use function trim;

use const FILTER_NULL_ON_FAILURE;
use const FILTER_VALIDATE_BOOLEAN;

/**
 * Provider GetOrganizationDashboardTrendProvider.
 *
 * @category Provider
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @implements ProviderInterface<OrganizationDashboardTrendOutput>
 */
final readonly class GetOrganizationDashboardTrendProvider implements ProviderInterface
{
  use UnwrapsOrganizationQueryExceptions;

  /**
   * @var array<string, string>
   */
  private const array OPERATION_METRICS = [
    OrganizationOperations::GET_ORGANIZATION_DASHBOARD_INSPECTIONS_TREND => GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED,
    OrganizationOperations::GET_ORGANIZATION_DASHBOARD_EQUIPMENT_CREATED_TREND => GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED,
    OrganizationOperations::GET_ORGANIZATION_DASHBOARD_FACILITIES_CREATED_TREND => GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED,
    OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_OPENED_TREND => GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_OPENED,
    OrganizationOperations::GET_ORGANIZATION_DASHBOARD_NON_CONFORMITIES_RESOLVED_TREND => GetOrganizationDashboardTrendHandler::METRIC_NON_CONFORMITIES_RESOLVED,
  ];

  /**
   * Constructor.
   *
   * Initializes the provider with the query bus,
   * authorization service, and current security context.
   *
   * @since 1.0.0
   *
   * @param QueryBusPort $queryBus the query bus for dispatching trend queries
   * @param OrganizationAuthorizationPort $authorization the organization authorization service
   * @param Security $security the security service exposing the current user
   */
  public function __construct(
    private QueryBusPort $queryBus,
    private OrganizationAuthorizationPort $authorization,
    private Security $security,
  ) {
  }

  /**
   * Builds a single-metric dashboard trend query from API Platform route variables and query-string filters.
   *
   * @param array<string, mixed> $uriVariables
   * @param array<string, mixed> $context
   *
   * @throws AccessDeniedHttpException when the user is not authenticated or misses a required permission
   * @throws BadRequestHttpException when the operation or one of the trend filters is invalid
   * @throws NotFoundHttpException when the target organization does not exist
   */
  public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?OrganizationDashboardTrendOutput
  {
    $user = $this->security->getUser();
    if (!$user instanceof SecurityUser) {
      throw new AccessDeniedHttpException('Authentication required.');
    }

    $organizationId = $uriVariables['organizationId'] ?? null;
    if (!is_string($organizationId) || '' === $organizationId) {
      return null;
    }

    $metric = $this->resolveMetric($operation);
    $this->assertMetricPermissions($user->getId(), $organizationId, $metric);

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

    $this->assertMetricScopedFilters(
      metric: $metric,
      facilityType: $facilityType,
      equipmentType: $equipmentType,
      equipmentStatus: $equipmentStatus,
      inspectionStatus: $inspectionStatus,
      inspectionResult: $inspectionResult,
      inspectorType: $inspectorType,
      nonConformityStatus: $nonConformityStatus,
      nonConformitySeverity: $nonConformitySeverity,
    );

    try {
      /** @var GetOrganizationDashboardTrendResult $result */
      $result = $this->queryBus->ask(new GetOrganizationDashboardTrendQuery(
        organizationId: $organizationId,
        userId: $user->getId(),
        metric: $metric,
        periodFrom: null !== $periodFrom ? $this->formatIso8601($periodFrom) : null,
        periodTo: null !== $periodTo ? $this->formatIso8601($periodTo) : null,
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

    $output = new OrganizationDashboardTrendOutput();
    $output->generatedAt = $result->generatedAt;
    $output->metric = $result->metric;
    $output->period = $result->period;
    $output->summary = $this->normalizeSummary($result->summary);
    $output->series = $result->series;
    $output->comparison = $this->normalizeComparison($result->comparison);

    return $output;
  }

  /**
   * Resolves the stable metric identifier associated with the current API operation.
   */
  private function resolveMetric(Operation $operation): string
  {
    $operationName = $operation->getName();
    if (!is_string($operationName) || !isset(self::OPERATION_METRICS[$operationName])) {
      throw new BadRequestHttpException('Unsupported dashboard trend operation.');
    }

    return self::OPERATION_METRICS[$operationName];
  }

  /**
   * Ensures the caller owns every permission required by the requested trend metric.
   */
  private function assertMetricPermissions(string $userId, string $organizationId, string $metric): void
  {
    foreach (OrganizationPermissionCatalog::dashboardTrendReadDependencies($metric) as $permission) {
      if (!$this->authorization->hasPermission($userId, $organizationId, $permission)) {
        throw new AccessDeniedHttpException(sprintf('Missing %s permission.', $permission));
      }
    }
  }

  /**
   * Ensures only the filter family documented for the requested
   * metric is accepted by the presentation layer.
   *
   * Inspection trends accept only inspection filters, while
   * non-conformity trends accept only non-conformity filters.
   *
   * @throws BadRequestHttpException when a filter unsupported by the selected metric is provided
   */
  private function assertMetricScopedFilters(
    string $metric,
    ?string $facilityType,
    ?string $equipmentType,
    ?string $equipmentStatus,
    ?string $inspectionStatus,
    ?string $inspectionResult,
    ?string $inspectorType,
    ?string $nonConformityStatus,
    ?string $nonConformitySeverity,
  ): void {
    match ($metric) {
      GetOrganizationDashboardTrendHandler::METRIC_INSPECTIONS_PERFORMED => $this->assertUnsupportedMetricFilters(
        'inspection trend',
        [
          'facilityType' => $facilityType,
          'equipmentType' => $equipmentType,
          'equipmentStatus' => $equipmentStatus,
          'nonConformityStatus' => $nonConformityStatus,
          'nonConformitySeverity' => $nonConformitySeverity,
        ],
      ),
      GetOrganizationDashboardTrendHandler::METRIC_EQUIPMENT_CREATED => $this->assertUnsupportedMetricFilters(
        'equipment-created trend',
        [
          'facilityType' => $facilityType,
          'inspectionStatus' => $inspectionStatus,
          'inspectionResult' => $inspectionResult,
          'inspectorType' => $inspectorType,
          'nonConformityStatus' => $nonConformityStatus,
          'nonConformitySeverity' => $nonConformitySeverity,
        ],
      ),
      GetOrganizationDashboardTrendHandler::METRIC_FACILITIES_CREATED => $this->assertUnsupportedMetricFilters(
        'facilities-created trend',
        [
          'equipmentType' => $equipmentType,
          'equipmentStatus' => $equipmentStatus,
          'inspectionStatus' => $inspectionStatus,
          'inspectionResult' => $inspectionResult,
          'inspectorType' => $inspectorType,
          'nonConformityStatus' => $nonConformityStatus,
          'nonConformitySeverity' => $nonConformitySeverity,
        ],
      ),
      default => $this->assertUnsupportedMetricFilters(
        'non-conformity trend',
        [
          'facilityType' => $facilityType,
          'equipmentType' => $equipmentType,
          'equipmentStatus' => $equipmentStatus,
          'inspectionStatus' => $inspectionStatus,
          'inspectionResult' => $inspectionResult,
          'inspectorType' => $inspectorType,
        ],
      ),
    };
  }

  /**
   * @param array<string, ?string> $filters
   */
  private function assertUnsupportedMetricFilters(string $metricLabel, array $filters): void
  {
    foreach ($filters as $name => $value) {
      if (null !== $value) {
        throw new BadRequestHttpException(sprintf(
          'Filter "%s" is not supported for the %s.',
          $name,
          $metricLabel,
        ));
      }
    }
  }

  /**
   * @return array<string, mixed>
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
   * @param array<string, mixed> $filters
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
   * @param array<string, mixed> $filters
   * @param list<string> $allowedValues
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
   * @param array<string, int|float> $summary
   *
   * @return array<string, int|float|string>
   */
  private function normalizeSummary(array $summary): array
  {
    return $summary + ['unit' => 'count'];
  }

  /**
   * @param array<string, mixed> $comparison
   *
   * @return array{mode: string, from: ?string, to: ?string, summary: array{total?: int, delta?: float}, series: list<array{bucket: string, value: int}>}
   */
  private function normalizeComparison(array $comparison): array
  {
    $mode = is_string($comparison['mode'] ?? null) ? $comparison['mode'] : 'none';
    $from = is_string($comparison['from'] ?? null) ? $comparison['from'] : null;
    $to = is_string($comparison['to'] ?? null) ? $comparison['to'] : null;
    $summary = is_array($comparison['summary'] ?? null) ? $comparison['summary'] : [];
    $normalizedSummary = [];
    if (isset($summary['total']) && (is_int($summary['total']) || is_float($summary['total']))) {
      $normalizedSummary['total'] = (int) $summary['total'];
    }
    if (isset($summary['delta']) && (is_float($summary['delta']) || is_int($summary['delta']))) {
      $normalizedSummary['delta'] = (float) $summary['delta'];
    }

    $series = [];
    $comparisonSeries = $comparison['series'] ?? [];
    if (!is_array($comparisonSeries)) {
      $comparisonSeries = [];
    }

    foreach ($comparisonSeries as $point) {
      if (
        is_array($point)
        && isset($point['bucket'], $point['value'])
        && is_string($point['bucket'])
        && is_int($point['value'])
      ) {
        $series[] = [
          'bucket' => $point['bucket'],
          'value' => $point['value'],
        ];
      }
    }

    return [
      'mode' => $mode,
      'from' => $from,
      'to' => $to,
      'summary' => $normalizedSummary,
      'series' => $series,
    ];
  }

  /**
   * Formats a datetime for transport while preserving microseconds when present.
   */
  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u')
      ? $value->format('Y-m-d\TH:i:sP')
      : $value->format('Y-m-d\TH:i:s.uP');
  }
}
