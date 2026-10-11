<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport;

use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\{ExportMaintenanceSchedulesQuery, ExportMaintenanceSchedulesResult};
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

/**
 * Class PrepareMaintenanceScheduleExportHandler
 *
 * Resolves the bounded export through its owner query and records export provenance after success.
 *
 * @category Handler
 */
final readonly class PrepareMaintenanceScheduleExportHandler implements CommandHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param QueryBusPort $queryBus owner-local export reads
   * @param EventDispatcherPort $eventDispatcher export provenance delivery
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queryBus, private EventDispatcherPort $eventDispatcher)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param PrepareMaintenanceScheduleExportCommand $command actor, scope and export filters
   *
   * @return ExportMaintenanceSchedulesResult bounded and grouped export rows
   */
  public function __invoke(PrepareMaintenanceScheduleExportCommand $command): ExportMaintenanceSchedulesResult
  {
    /** @var ExportMaintenanceSchedulesResult $result */
    $result = $this->queryBus->ask(new ExportMaintenanceSchedulesQuery(
      userId: $command->userId,
      organizationId: $command->organizationId,
      facilityId: $command->facilityId,
      equipmentType: $command->equipmentType,
      dueStatus: $command->dueStatus,
      dueBefore: $command->dueBefore,
    ));
    $filterKeys = [];
    foreach (['facilityId', 'equipmentType', 'dueStatus', 'dueBefore'] as $key) {
      if (null !== $command->{$key}) {
        $filterKeys[] = $key;
      }
    }
    $this->eventDispatcher->dispatch(new MaintenanceSchedulesExportedEvent(
      organizationId: $command->organizationId,
      actorUserId: $command->userId,
      format: 'csv',
      rowCount: $result->total,
      filterKeys: $filterKeys,
    ));

    return $result;
  }
  // #endregion
}
