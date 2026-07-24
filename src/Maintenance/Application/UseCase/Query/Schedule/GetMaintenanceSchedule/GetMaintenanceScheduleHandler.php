<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Schedule\GetMaintenanceSchedule;

use Maintenance\Application\Port\Outbound\Schedule\MaintenanceScheduleRepositoryPort;
use Maintenance\Domain\Exception\{MaintenanceAccessDeniedException, MaintenanceNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase GetMaintenanceScheduleHandler.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetMaintenanceScheduleHandler implements QueryHandler
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
   * @param GetMaintenanceScheduleQuery $query the query value
   *
   * @return GetMaintenanceScheduleResult the query result
   */
  public function __invoke(GetMaintenanceScheduleQuery $query): GetMaintenanceScheduleResult
  {
    $schedule = $this->schedules->findById($query->scheduleId);
    if (null === $schedule) {
      throw MaintenanceNotFoundException::withId($query->scheduleId);
    }

    if (!$this->authorization->hasPermission($query->userId, $schedule->organizationId, 'organization.maintenance.read')) {
      throw new MaintenanceAccessDeniedException('Missing organization.maintenance.read permission.');
    }

    return new GetMaintenanceScheduleResult($schedule);
  }
}
