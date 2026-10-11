<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\RecordInspectionExport;

use Inspection\Application\Contract\Event\Export\{InspectionReportExportedEvent, InspectionsExportedEvent, NonConformitiesExportedEvent, NonConformitiesReportExportedEvent};
use Shared\Application\Message\CommandMessage;

/** Records the completed export as a durable integration event.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInspectionExportCommand implements CommandMessage
{
  public function __construct(public InspectionsExportedEvent|NonConformitiesExportedEvent|InspectionReportExportedEvent|NonConformitiesReportExportedEvent $event)
  {
  }
}
