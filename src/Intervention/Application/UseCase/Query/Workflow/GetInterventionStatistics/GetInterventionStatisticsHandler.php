<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics;

use Intervention\Application\Contract\Statistics\{InterventionIdentifierCount, InterventionStatisticsResponsibleEntry, InterventionStatisticsSiteEntry};
use Intervention\Application\Port\Outbound\{InterventionMemberNamingPort, InterventionSiteNamingPort, InterventionStatisticsGatewayPort};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Outbound\ClockPort;

use function array_map;

/**
 * UseCase GetInterventionStatisticsHandler.
 *
 * Backs the list page's KPI cards and the kanban's column counters: a
 * whole-organization snapshot, always covering every status/priority key so
 * the kanban never has to guess a column is empty versus absent.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionStatisticsHandler implements QueryHandler
{
  // #region Constants
  /**
   * Every `InterventionStatus` literal, in the stable order the kanban
   * expects. Zero-filled here so a status with no intervention still appears
   * as a `0` key, never an absent one.
   *
   * @var list<string>
   */
  private const array STATUS_KEYS = [
    'draft',
    'planned',
    'in_progress',
    'submitted',
    'changes_requested',
    'published',
    'abandoned',
  ];

  /**
   * Every `InterventionPriority` literal.
   *
   * @var list<string>
   */
  private const array PRIORITY_KEYS = ['low', 'normal', 'high', 'urgent'];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param InterventionStatisticsGatewayPort $statistics the statistics gateway port
   * @param InterventionSiteNamingPort $siteNaming the site naming port
   * @param InterventionMemberNamingPort $memberNaming the member naming port
   * @param OrganizationAuthorizationPort $authorization the authorization port
   * @param ClockPort $clock the clock port
   */
  public function __construct(
    private InterventionStatisticsGatewayPort $statistics,
    private InterventionSiteNamingPort $siteNaming,
    private InterventionMemberNamingPort $memberNaming,
    private OrganizationAuthorizationPort $authorization,
    private ClockPort $clock,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GetInterventionStatisticsQuery $query the query value
   *
   * @return GetInterventionStatisticsResult the statistics snapshot
   */
  public function __invoke(GetInterventionStatisticsQuery $query): GetInterventionStatisticsResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.interventions.read');
    if ($decision->isOutsideScope()) {
      throw InterventionNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new InterventionAccessDeniedException('Missing organization.interventions.read permission.');
    }

    $aggregate = $this->statistics->aggregate($query->organizationId, $this->clock->now());

    $byStatus = [];
    foreach (self::STATUS_KEYS as $status) {
      $byStatus[$status] = $aggregate->countsByStatus[$status] ?? 0;
    }

    $byPriority = [];
    foreach (self::PRIORITY_KEYS as $priority) {
      $byPriority[$priority] = $aggregate->countsByPriority[$priority] ?? 0;
    }

    $siteNames = $this->siteNaming->findNamesByIds(array_map(
      static fn (InterventionIdentifierCount $entry): string => $entry->id,
      $aggregate->topSites,
    ));
    $bySite = array_map(
      static fn (InterventionIdentifierCount $entry): InterventionStatisticsSiteEntry => new InterventionStatisticsSiteEntry(
        siteId: $entry->id,
        siteName: $siteNames[$entry->id] ?? null,
        count: $entry->count,
      ),
      $aggregate->topSites,
    );

    $responsibleNames = $this->memberNaming->displayNamesFor($query->organizationId, array_map(
      static fn (InterventionIdentifierCount $entry): string => $entry->id,
      $aggregate->topResponsibles,
    ));
    $byResponsible = array_map(
      static fn (InterventionIdentifierCount $entry): InterventionStatisticsResponsibleEntry => new InterventionStatisticsResponsibleEntry(
        memberId: $entry->id,
        displayName: $responsibleNames[$entry->id] ?? null,
        count: $entry->count,
      ),
      $aggregate->topResponsibles,
    );

    return new GetInterventionStatisticsResult(
      total: $aggregate->total,
      byStatus: $byStatus,
      byPriority: $byPriority,
      overdue: $aggregate->overdue,
      dueSoon: $aggregate->dueSoon,
      bySite: $bySite,
      byResponsible: $byResponsible,
      averagePublicationDays: $aggregate->averagePublicationDays,
    );
  }
  // #endregion
}
