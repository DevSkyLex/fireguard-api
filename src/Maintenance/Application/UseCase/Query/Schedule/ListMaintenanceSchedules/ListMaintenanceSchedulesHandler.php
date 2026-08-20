<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\ListMaintenanceSchedules;

use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListMaintenanceSchedulesHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMaintenanceSchedulesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MaintenanceScheduleRepositoryPort $schedules the schedule repository port
   * @param OrganizationAuthorizationPort $authorization the organization authorization port
   */
  public function __construct(
    private MaintenanceScheduleRepositoryPort $schedules,
    private OrganizationAuthorizationPort $authorization,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListMaintenanceSchedulesQuery $query the query value
   *
   * @return ListMaintenanceSchedulesResult the query result
   */
  public function __invoke(ListMaintenanceSchedulesQuery $query): ListMaintenanceSchedulesResult
  {
    $decision = $this->authorization->resolveAccess($query->userId, $query->organizationId, 'organization.maintenance.read');
    if ($decision->isOutsideScope()) {
      throw MaintenanceNotFoundException::forOrganizationScope($query->organizationId);
    }
    if (!$decision->isGranted()) {
      throw new MaintenanceAccessDeniedException('Missing organization.maintenance.read permission.');
    }

    return new ListMaintenanceSchedulesResult($this->schedules->list(
      $query->organizationId,
      $query->facilityId,
      $query->equipmentType,
      $query->dueStatus,
      $query->dueBefore,
      $query->page,
      $query->itemsPerPage,
    ));
  }
}
