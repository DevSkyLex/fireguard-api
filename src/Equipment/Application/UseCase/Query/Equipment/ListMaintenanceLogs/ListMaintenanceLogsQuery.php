<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMaintenanceLogsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceLogsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
