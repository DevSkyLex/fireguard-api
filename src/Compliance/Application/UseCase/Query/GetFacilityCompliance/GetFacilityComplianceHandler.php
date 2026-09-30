<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetFacilityCompliance;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Service\ComplianceRegisterAggregator;
use Compliance\Domain\Exception\{ComplianceAccessDeniedException, ComplianceNotFoundException};
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function hash;

/**
 * UseCase GetFacilityComplianceHandler.
 *
 * Reuses `ComplianceRegisterAggregator::buildFacilityViews()` (an org-wide,
 * already-grouped aggregate read, cheap to recompute for a single facility)
 * and picks the requested facility, throwing
 * {@see ComplianceNotFoundException} when it is not part of the
 * organization's facility directory.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityComplianceHandler implements QueryHandler
{
  // #region Constants
  /**
   * Constant DEFAULT_CACHE_TTL_SECONDS
   *
   * Default lifetime for a cached facility compliance result.
   *
   * @access private
   *
   * @var int
   */
  private const int DEFAULT_CACHE_TTL_SECONDS = 60;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   * @param ComplianceRegisterAggregator $aggregator the compliance register aggregator
   * @param ?CachePort $cache the optional cache port
   * @param int $cacheTtl the cache TTL in seconds
   */
  public function __construct(
    private OrganizationAuthorizationPort $authorization,
    private ComplianceRegisterAggregator $aggregator,
    private ?CachePort $cache = null,
    private int $cacheTtl = self::DEFAULT_CACHE_TTL_SECONDS,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetFacilityComplianceQuery $query the facility compliance query
   *
   * @return GetFacilityComplianceResult the single-facility compliance view
   *
   * @throws ComplianceAccessDeniedException if the user lacks a required permission
   * @throws ComplianceNotFoundException if the facility is not part of the organization's directory
   */
  public function __invoke(GetFacilityComplianceQuery $query): GetFacilityComplianceResult
  {
    $this->assertPermissions($query->userId, $query->organizationId);

    $cacheKey = $this->buildCacheKey($query->organizationId, $query->facilityId);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetFacilityComplianceResult) {
      return $cached;
    }

    $facility = $this->findFacility($query->organizationId, $query->facilityId);

    $result = new GetFacilityComplianceResult(
      generatedAt: $this->formatIso8601(new DateTimeImmutable()),
      facility: $facility,
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  /**
   * @throws ComplianceNotFoundException if the facility is not part of the organization's directory
   */
  private function findFacility(string $organizationId, string $facilityId): FacilityComplianceView
  {
    foreach ($this->aggregator->buildFacilityViews($organizationId) as $view) {
      if ($view->facilityId === $facilityId) {
        return $view;
      }
    }

    throw ComplianceNotFoundException::facilityNotFound($facilityId);
  }

  /**
   * Method assertPermissions
   *
   * Requires the permissions needed to read compliance data.
   *
   * @access private
   *
   * @param string $userId requesting user identifier
   * @param string $organizationId organization scope
   *
   * @return void
   *
   * @throws ComplianceAccessDeniedException when the authorization check denies access
   */
  private function assertPermissions(string $userId, string $organizationId): void
  {
    try {
      $this->authorization->assertGrantedPermissions(
        $userId,
        $organizationId,
        OrganizationPermissionCatalog::complianceReadDependencies(),
      );
    } catch (OrganizationAccessDeniedException $exception) {
      throw new ComplianceAccessDeniedException($exception->getMessage(), 0, $exception);
    }
  }

  /**
   * Method buildCacheKey
   *
   * Builds a cache key scoped to the organization and facility identifiers.
   *
   * @access private
   *
   * @param string $organizationId organization scope
   * @param string $facilityId facility identifier
   *
   * @return string cache key
   */
  private function buildCacheKey(string $organizationId, string $facilityId): string
  {
    return 'compliance.facility.v2.' . hash('sha256', $organizationId . '|' . $facilityId);
  }

  /**
   * Method readCache
   *
   * Returns a cached result of the expected type when caching is enabled.
   *
   * @access private
   *
   * @param string $cacheKey facility compliance cache key
   *
   * @return ?GetFacilityComplianceResult cached result, if available
   */
  private function readCache(string $cacheKey): ?GetFacilityComplianceResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetFacilityComplianceResult ? $cached : null;
  }

  /**
   * Method writeCache
   *
   * Stores the result when a cache is configured and its lifetime is positive.
   *
   * @access private
   *
   * @param string $cacheKey facility compliance cache key
   * @param GetFacilityComplianceResult $result result to store
   *
   * @return void
   */
  private function writeCache(string $cacheKey, GetFacilityComplianceResult $result): void
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return;
    }

    try {
      $this->cache->set($cacheKey, $result, $this->cacheTtl);
    } catch (Throwable) {
      // Compliance register cache failures should not block a fresh read.
    }
  }

  /**
   * Method formatIso8601
   *
   * Formats a timestamp with fractional seconds only when present.
   *
   * @access private
   *
   * @param DateTimeImmutable $value timestamp to format
   *
   * @return string ISO 8601 timestamp
   */
  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
  }
  // #endregion
}
