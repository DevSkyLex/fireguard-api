<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Query\Workflow\GetInterventionStatistics;

use Intervention\Application\Contract\Statistics\{InterventionStatisticsResponsibleEntry, InterventionStatisticsSiteEntry};
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetInterventionStatisticsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetInterventionStatisticsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param int $total total intervention count for the organization, every status included
   * @param array<string,int> $byStatus exactly the seven `InterventionStatus` literals as keys, zero included — stable for the kanban's column counters
   * @param array<string,int> $byPriority exactly the four `InterventionPriority` literals as keys, zero included
   * @param int $overdue count of non-terminal interventions whose `dueAt` is in the past
   * @param int $dueSoon count of interventions in a still-active status whose `dueAt` falls within the reminder sweep's 48h window
   * @param list<InterventionStatisticsSiteEntry> $bySite the top 10 sites by intervention count, descending
   * @param list<InterventionStatisticsResponsibleEntry> $byResponsible the top 10 responsible members by intervention count, descending
   * @param ?float $averagePublicationDays mean days between draft creation and publication, across every published intervention; null when none are published
   */
  public function __construct(
    public int $total,
    public array $byStatus,
    public array $byPriority,
    public int $overdue,
    public int $dueSoon,
    public array $bySite,
    public array $byResponsible,
    public ?float $averagePublicationDays,
  ) {
  }
  // #endregion
}
