<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Command\Export\PrepareMaintenanceScheduleExport;

use DateTimeImmutable;
use Maintenance\Application\Port\Outbound\Export\MaintenanceScheduleCsvRendererPort;
use Maintenance\Application\UseCase\Query\ExportMaintenanceSchedules\{ExportMaintenanceSchedulesQuery, ExportMaintenanceSchedulesResult};
use Maintenance\Domain\Event\Export\MaintenanceSchedulesExportedEvent;
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Inbound\QueryBusPort;
use Shared\Application\Port\Outbound\EventDispatcherPort;

use function base64_encode;
use function sprintf;

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
   * @param EventDispatcherPort $eventDispatcher explicitly main export provenance delivery
   * @param MaintenanceScheduleCsvRendererPort $renderer original CSV capture before provenance and commit
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queryBus, private EventDispatcherPort $eventDispatcher, private MaintenanceScheduleCsvRendererPort $renderer)
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
   * @return PrepareMaintenanceScheduleExportResult original CSV bytes and filename
   */
  public function __invoke(PrepareMaintenanceScheduleExportCommand $command): PrepareMaintenanceScheduleExportResult
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
    $prepared = new PrepareMaintenanceScheduleExportResult(
      base64_encode($this->renderer->render($result->rows)),
      sprintf('maintenance-schedules-export-%s.csv', new DateTimeImmutable()->format('Ymd-His')),
    );
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

    return $prepared;
  }
  // #endregion
}
