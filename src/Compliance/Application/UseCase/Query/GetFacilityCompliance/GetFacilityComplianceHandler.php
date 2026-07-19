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
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityComplianceHandler implements QueryHandler
{
  // #region Constants
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
   * @throws ComplianceAccessDeniedException if the user lacks a required permission
   * @throws ComplianceNotFoundException if the facility is not part of the organization's directory
   *
   * @return GetFacilityComplianceResult the single-facility compliance view
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

  private function buildCacheKey(string $organizationId, string $facilityId): string
  {
    return 'compliance.facility.' . hash('sha256', $organizationId . '|' . $facilityId);
  }

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

  private function formatIso8601(DateTimeImmutable $value): string
  {
    return '000000' === $value->format('u') ? $value->format('Y-m-d\\TH:i:sP') : $value->format('Y-m-d\\TH:i:s.uP');
  }
  // #endregion
}
