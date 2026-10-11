<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\RecordInterventionExport;

use Intervention\Application\Contract\Event\Export\InterventionsExportedEvent;
use Intervention\Application\Contract\Event\InterventionReportExportedEvent;
use Shared\Application\Message\CommandMessage;

/** Records the completed export as a durable integration event.
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RecordInterventionExportCommand implements CommandMessage
{
  public function __construct(public InterventionsExportedEvent|InterventionReportExportedEvent $event)
  {
  }
}
