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
use Organization\Presentation\Api\Support\{OrganizationDashboardOutputNormalizer, UnwrapsOrganizationBusFailures};
use Shared\Application\Exception\MessengerRuntimeException;
use Shared\Application\Port\Inbound\QueryBusPort;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;
use User\Application\UseCase\Query\User\GetUser\{GetUserQuery, GetUserResult};

use function array_column;
use function array_keys;
use function filter_var;
use function implode;
use function in_array;
use function is_array;
use function is_bool;
use function is_string;
use function sprintf;
use function timezone_identifiers_list;
use function trim;

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
   * Trait UnwrapsOrganizationBusFailures.
   *
   * This trait provides utility methods to unwrap exceptions thrown by the query bus
   * and identify specific domain exceptions related to organization queries.
   *
   * It allows the provider to catch generic messenger exceptions and rethrow more specific HTTP exceptions
   * based on the underlying cause, improving error handling and client feedback.
   *
   * @see UnwrapsOrganizationBusFailures
   */
  use UnwrapsOrganizationBusFailures;

  private const string INVALID_BOOLEAN_FILTER_MESSAGE = 'Invalid "%s" filter. Allowed values: true, false, 1, 0, yes, no, on, off.';
  // #endregion

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

    $filters = $this->normalizeFilters(\Shared\Presentation\Api\Http\OperationParameterReader::filters($operation, $context));

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
    $overviewPrimaryMetricKeys = OrganizationDashboardOutputNormalizer::resolveOverviewPrimaryMetricKeys(
      equipmentStatus: $equipmentStatus,
      inspectionStatus: $inspectionStatus,
      nonConformityStatus: $nonConformityStatus,
    );

    try {
      /**
       * @var GetOrganizationDashboardResult $result
       */
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

    $normalizedOverview = OrganizationDashboardOutputNormalizer::normalizeOverview($result->overview, $overviewPrimaryMetricKeys);
    $normalizedHealth = OrganizationDashboardOutputNormalizer::normalizeHealth($result->health);
    $normalizedAlerts = OrganizationDashboardOutputNormalizer::normalizeAlerts($result->alerts);
    $normalizedComparison = OrganizationDashboardOutputNormalizer::normalizeComparison($result->comparison);

    $output = new OrganizationDashboardOutput();
    $output->generatedAt = $result->generatedAt;
    $output->period = $result->period;
    $output->overview = $normalizedOverview;
    $output->health = $normalizedHealth;
    $output->alerts = $normalizedAlerts;
    $output->comparison = $normalizedComparison;
    $output->trends = OrganizationDashboardOutputNormalizer::normalizeTrends($result->trends);
    $output->recentInterventions = $this->normalizeRecentInterventions($result->recentInterventions);

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
        self::INVALID_BOOLEAN_FILTER_MESSAGE,
        $name,
      ));
    }

    $value = trim($value);
    if ('' === $value) {
      throw new BadRequestHttpException(sprintf(
        self::INVALID_BOOLEAN_FILTER_MESSAGE,
        $name,
      ));
    }

    $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    if (null !== $parsed) {
      return $parsed;
    }

    throw new BadRequestHttpException(sprintf(
      self::INVALID_BOOLEAN_FILTER_MESSAGE,
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
   * Method normalizeRecentInterventions.
   *
   * Enriches the handler's recent-interventions rows with the
   * responsible member's resolved display name and avatar URL. Mirrors
   * `ListOrganizationMembersProvider::findUser`'s batch-dedupe pattern
   * and fallback chain (`trim(first + last) ?: username ?: member id`).
   *
   * @since 1.0.0
   *
   * @param list<array{
   *   id: string,
   *   number: int,
   *   name: string,
   *   status: string,
   *   priority: string,
   *   siteId: ?string,
   *   siteName: ?string,
   *   responsibleId: ?string,
   *   responsibleUserId: ?string,
   *   dueAt: ?string,
   *   updatedAt: string,
   * }> $recentInterventions
   *
   * @return list<array{
   *   id: string,
   *   number: int,
   *   name: string,
   *   status: string,
   *   priority: string,
   *   siteId: ?string,
   *   siteName: ?string,
   *   responsibleId: ?string,
   *   responsibleName: ?string,
   *   responsibleAvatarUrl: ?string,
   *   dueAt: ?string,
   *   updatedAt: string,
   * }>
   */
  private function normalizeRecentInterventions(array $recentInterventions): array
  {
    if ([] === $recentInterventions) {
      return [];
    }

    $userIds = [];
    foreach ($recentInterventions as $row) {
      if (null !== $row['responsibleUserId']) {
        $userIds[$row['responsibleUserId']] = true;
      }
    }

    $usersById = [];
    foreach (array_keys($userIds) as $userId) {
      $usersById[$userId] = $this->findUser($userId);
    }

    $normalized = [];
    foreach ($recentInterventions as $row) {
      $userResult = null !== $row['responsibleUserId'] ? ($usersById[$row['responsibleUserId']] ?? null) : null;
      [$responsibleName, $responsibleAvatarUrl] = $this->responsibleProfile($userResult, $row['responsibleId']);

      $normalized[] = [
        'id' => $row['id'],
        'number' => $row['number'],
        'name' => $row['name'],
        'status' => $row['status'],
        'priority' => $row['priority'],
        'siteId' => $row['siteId'],
        'siteName' => $row['siteName'],
        'responsibleId' => $row['responsibleId'],
        'responsibleName' => $responsibleName,
        'responsibleAvatarUrl' => $responsibleAvatarUrl,
        'dueAt' => $row['dueAt'],
        'updatedAt' => $row['updatedAt'],
      ];
    }

    return $normalized;
  }

  /**
   * @return array{?string, ?string}
   */
  private function responsibleProfile(?GetUserResult $userResult, ?string $responsibleId): array
  {
    if ($userResult instanceof GetUserResult && null !== $userResult->user) {
      $name = trim($userResult->user->firstName . ' ' . $userResult->user->lastName)
        ?: $userResult->user->username
        ?: $responsibleId;

      return [$name, $userResult->user->avatarUrl];
    }

    return [$responsibleId, null];
  }

  /**
   * Resolves a user profile without making the dashboard fail when the
   * user record is unavailable.
   */
  private function findUser(string $userId): ?GetUserResult
  {
    try {
      /**
       * @var GetUserResult $result
       */
      $result = $this->queryBus->ask(new GetUserQuery($userId));
    } catch (Throwable) {
      return null;
    }

    return $result;
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
