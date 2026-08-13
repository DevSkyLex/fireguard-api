<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Statistics;

/**
 * Contract InterventionStatisticsAggregate.
 *
 * Raw organization-wide intervention statistics, as computed by
 * {@see \Intervention\Application\Port\Outbound\InterventionStatisticsGatewayPort}.
 * Deliberately sparse and unresolved: `countsByStatus`/`countsByPriority` carry
 * only the keys that had at least one row, and `topSites`/`topResponsibles`
 * carry bare identifiers, never names — zero-filling every status/priority key
 * and resolving display names is the handler's job
 * ({@see \Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics\GetInterventionStatisticsHandler}),
 * not the gateway's.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionStatisticsAggregate
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $total total intervention count for the organization, every status included
   * @param array<string,int> $countsByStatus counts keyed by the raw `InterventionStatus` literal; absent key means zero
   * @param array<string,int> $countsByPriority counts keyed by the raw `InterventionPriority` literal; absent key means zero
   * @param int $overdue count of non-terminal interventions whose `dueAt` is in the past
   * @param int $dueSoon count of interventions in a still-active status whose `dueAt` falls within the reminder sweep's 48h window
   * @param list<InterventionIdentifierCount> $topSites the top site identifiers by intervention count, descending, bounded
   * @param list<InterventionIdentifierCount> $topResponsibles the top responsible member identifiers by intervention count, descending, bounded
   * @param ?float $averagePublicationDays mean days between draft creation and publication, across every published intervention; null when none are published
   */
  public function __construct(
    public int $total,
    public array $countsByStatus,
    public array $countsByPriority,
    public int $overdue,
    public int $dueSoon,
    public array $topSites,
    public array $topResponsibles,
    public ?float $averagePublicationDays,
  ) {
  }
  // #endregion
}
