<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase ListMaintenanceLogsResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceLogsResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param list<array{id: string, equipmentId: string, organizationId: string, startedAt: string, completedAt: ?string, source: string, interventionId: ?string, interventionNumber: ?int, workItemAction: ?string, actorId: ?string, summary: ?string}> $logs the log list
   * @param int $total the total count unaffected by pagination
   */
  public function __construct(
    public array $logs,
    public int $total,
  ) {
  }
  // #endregion
}
