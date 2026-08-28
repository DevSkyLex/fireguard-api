<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformityStatistics;

use Inspection\Application\Contract\Statistics\{NonConformityFacilityCount, NonConformityStatisticsFacilityEntry};
use Inspection\Application\Port\Outbound\{FacilityNamingPort, NonConformityStatisticsGatewayPort};
use Inspection\Domain\Exception\{InspectionAccessDeniedException, InspectionNotFoundException};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

use function array_map;

/**
 * UseCase GetNonConformityStatisticsHandler.
 *
 * Organization-wide non-conformity statistics for the compliance KPI
 * surfaces: severity × open/resolved matrix (all four severity keys, zeros
 * included), top facilities and equipment types by open count, resolution
 * time metrics, and the open SLA-breached counter. "Open" is status
 * `open`/`in_progress`; "resolved" is `done`/`waived` — the same split the
 * navigation counters and the dashboard use.
 *
 * Access mirrors `ExportNonConformitiesHandler`: one `resolveAccess()` on
 * `organization.inspection.read`, 404 for a caller outside the
 * organization's scope, 403 for an unentitled member.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetNonConformityStatisticsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Every `NonConformitySeverity` literal, in the stable order the frontend
   * charts expect. Zero-filled here so a severity with no non-conformity
   * still appears as `{open: 0, resolved: 0}`, never an absent key.
   *
   * @var list<string>
   */
  private const array SEVERITY_KEYS = ['low', 'medium', 'high', 'critical'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param NonConformityStatisticsGatewayPort $statistics the statistics gateway port
   * @param FacilityNamingPort $facilityNaming the facility naming port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private NonConformityStatisticsGatewayPort $statistics,
    private FacilityNamingPort $facilityNaming,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetNonConformityStatisticsQuery $query the query value
   *
   * @throws InvalidArgumentException when the window bounds are inverted
   * @throws InspectionNotFoundException when the caller is outside the organization's scope
   * @throws InspectionAccessDeniedException when the caller lacks `organization.inspection.read`
   *
   * @return GetNonConformityStatisticsResult the statistics snapshot
   */
  public function __invoke(GetNonConformityStatisticsQuery $query): GetNonConformityStatisticsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.inspection.read');
    if ($decision->isOutsideScope()) {
      throw InspectionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InspectionAccessDeniedException('Missing organization.inspection.read permission.');
    }

    if (null !== $query->from && null !== $query->to && $query->from > $query->to) {
      throw new InvalidArgumentException('The from bound must not be after the to bound.');
    }

    $aggregate = $this->statistics->aggregate($query->organizationId, $query->from, $query->to);

    $bySeverity = [];
    foreach (self::SEVERITY_KEYS as $severity) {
      $bucket = $aggregate->bySeverity[$severity] ?? null;
      $bySeverity[$severity] = [
        'open' => null !== $bucket ? $bucket->open : 0,
        'resolved' => null !== $bucket ? $bucket->resolved : 0,
      ];
    }

    $facilityNames = $this->facilityNaming->findNamesByIds(array_map(
      static fn (NonConformityFacilityCount $entry): string => $entry->facilityId,
      $aggregate->topFacilities,
    ));
    $byFacility = array_map(
      static fn (NonConformityFacilityCount $entry): NonConformityStatisticsFacilityEntry => new NonConformityStatisticsFacilityEntry(
        facilityId: $entry->facilityId,
        facilityName: $facilityNames[$entry->facilityId] ?? null,
        open: $entry->open,
        critical: $entry->critical,
      ),
      $aggregate->topFacilities,
    );

    return new GetNonConformityStatisticsResult(
      bySeverity: $bySeverity,
      byFacility: $byFacility,
      byEquipmentType: $aggregate->topEquipmentTypes,
      averageResolutionDays: $aggregate->averageResolutionDays,
      medianResolutionDays: $aggregate->medianResolutionDays,
      slaBreachedOpen: $aggregate->slaBreachedOpen,
    );
  }
  // #endregion
}
