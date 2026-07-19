<?php

declare(strict_types=1);

namespace Compliance\Application\UseCase\Query\GetComplianceOverview;

use Compliance\Application\Contract\FacilityComplianceView;
use Compliance\Application\Service\{ComplianceRegisterAggregator, ComplianceStatusPolicy};
use Compliance\Domain\Exception\ComplianceAccessDeniedException;
use Compliance\Domain\ValueObject\ComplianceStatus;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Catalog\OrganizationPermissionCatalog;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\CachePort;
use Throwable;

use function array_map;
use function hash;

/**
 * UseCase GetComplianceOverviewHandler.
 *
 * Mirrors `GetOrganizationDashboardHandler`'s permission-then-cache-then-fan-out
 * flow: asserts `organization.compliance.read` (plus its read-side
 * dependencies) before touching any cross-module port, then serves a
 * permission-aware cached aggregate when available.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetComplianceOverviewHandler implements QueryHandler
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
   * @param GetComplianceOverviewQuery $query the compliance overview query
   *
   * @throws ComplianceAccessDeniedException if the user lacks a required permission
   *
   * @return GetComplianceOverviewResult the organization compliance register
   */
  public function __invoke(GetComplianceOverviewQuery $query): GetComplianceOverviewResult
  {
    $this->assertPermissions($query->userId, $query->organizationId);

    $cacheKey = $this->buildCacheKey($query->organizationId);
    $cached = $this->readCache($cacheKey);
    if ($cached instanceof GetComplianceOverviewResult) {
      return $cached;
    }

    $facilities = $this->aggregator->buildFacilityViews($query->organizationId);
    $organizationStatus = ComplianceStatusPolicy::worstOf(array_map(
      static fn (FacilityComplianceView $view): ComplianceStatus => $view->status,
      $facilities,
    ));

    $totals = [
      'totalEquipmentCount' => 0,
      'activeEquipmentCount' => 0,
      'upToDateEquipmentCount' => 0,
      'dueSoonEquipmentCount' => 0,
      'overdueEquipmentCount' => 0,
      'unscheduledEquipmentCount' => 0,
      'trackedEquipmentCount' => 0,
      'openLowNonConformityCount' => 0,
      'openMediumNonConformityCount' => 0,
      'openHighNonConformityCount' => 0,
      'openCriticalNonConformityCount' => 0,
    ];
    foreach ($facilities as $facility) {
      $totals['totalEquipmentCount'] += $facility->totalEquipmentCount;
      $totals['activeEquipmentCount'] += $facility->activeEquipmentCount;
      $totals['upToDateEquipmentCount'] += $facility->upToDateEquipmentCount;
      $totals['dueSoonEquipmentCount'] += $facility->dueSoonEquipmentCount;
      $totals['overdueEquipmentCount'] += $facility->overdueEquipmentCount;
      $totals['unscheduledEquipmentCount'] += $facility->unscheduledEquipmentCount;
      $totals['trackedEquipmentCount'] += $facility->trackedEquipmentCount();
      $totals['openLowNonConformityCount'] += $facility->openLowNonConformityCount;
      $totals['openMediumNonConformityCount'] += $facility->openMediumNonConformityCount;
      $totals['openHighNonConformityCount'] += $facility->openHighNonConformityCount;
      $totals['openCriticalNonConformityCount'] += $facility->openCriticalNonConformityCount;
    }

    // Org-wide rate from summed totals, NOT an average of per-facility rates
    // (that would misweight small vs large facilities) — same formula as
    // FacilityComplianceView::complianceRate(), null when nothing is tracked.
    $totals['complianceRate'] = FacilityComplianceView::computeComplianceRate(
      $totals['upToDateEquipmentCount'],
      $totals['trackedEquipmentCount'],
    );

    $result = new GetComplianceOverviewResult(
      generatedAt: $this->formatIso8601(new DateTimeImmutable()),
      organizationStatus: $organizationStatus,
      totals: $totals,
      facilities: $facilities,
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
    return 'compliance.overview.' . hash('sha256', $organizationId);
  }

  private function readCache(string $cacheKey): ?GetComplianceOverviewResult
  {
    if (null === $this->cache || $this->cacheTtl <= 0) {
      return null;
    }

    try {
      $cached = $this->cache->get($cacheKey);
    } catch (Throwable) {
      return null;
    }

    return $cached instanceof GetComplianceOverviewResult ? $cached : null;
  }

  private function writeCache(string $cacheKey, GetComplianceOverviewResult $result): void
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
