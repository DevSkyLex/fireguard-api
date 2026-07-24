<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetFacilityTree;

use Compliance\Application\Service\{ComplianceRegisterAggregator, FacilityTreeBuilder};
use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function hash;

/**
 * UseCase GetFacilityTreeHandler.
 *
 * Builds the enriched facility hierarchy (Site -> Building -> Floor ->
 * Zone/Area — "L2.9"), gated by the SAME permission set as the compliance
 * overview (`complianceReadDependencies()`), and reuses
 * `ComplianceRegisterAggregator::buildFacilityViews()` — the single,
 * already-batched (by facility-id list, never per node) source of the
 * compliance verdict — so this endpoint costs zero additional cross-module
 * port calls beyond what the overview already makes, and there remains
 * exactly one definition of "compliant".
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityTreeHandler implements QueryHandler
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
   * @param GetFacilityTreeQuery $query the facility tree query
   *
   * @throws ComplianceAccessDeniedException if the user lacks a required permission
   *
   * @return GetFacilityTreeResult the organization's enriched facility tree
   */
  public function __invoke(GetFacilityTreeQuery $query): GetFacilityTreeResult
  {
    $this->assertPermissions($query->userId, $query->organizationId);

    $cacheKey = $this->buildCacheKey($query->organizationId);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetFacilityTreeResult) {
      return $cached;
    }

    $facilities = $this->aggregator->buildFacilityViews($query->organizationId);
    $tree = FacilityTreeBuilder::build($facilities);

    $result = new GetFacilityTreeResult(
      generatedAt: $this->formatIso8601(new DateTimeImmutable()),
      tree: $tree,
    );
    $this->writeCache($cacheKey, $result);

    return $result;
  }

  /**
   * Method assertPermissions.
   *
   * @since 1.0.0
   *
   * @param string $userId the authenticated user identifier
   * @param string $organizationId the organization identifier
   *
   * @throws ComplianceAccessDeniedException if a required permission is missing
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

  private function buildCacheKey(string $organizationId): string
  {
    return 'compliance.facility-tree.' . hash('sha256', $organizationId);
  }

  private function readCache(string $cacheKey): ?GetFacilityTreeResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetFacilityTreeResult ? $cached : null;
  }

  private function writeCache(string $cacheKey, GetFacilityTreeResult $result): void
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
