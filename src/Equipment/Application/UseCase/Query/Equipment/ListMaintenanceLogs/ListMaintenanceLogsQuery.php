<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Query\Equipment\ListMaintenanceLogs;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMaintenanceLogsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceLogsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Identifies equipment within an organization and the requested page of its maintenance history.
   *
   * @access public
   *
   * @param string $organizationId organization scope used to validate the equipment
   * @param string $equipmentId equipment whose maintenance history is listed
   * @param Pagination $pagination requested page and page size
   *
   * @return void
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
