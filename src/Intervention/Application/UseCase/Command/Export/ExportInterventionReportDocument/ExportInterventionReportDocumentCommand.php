<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\ExportInterventionReportDocument;

use Shared\Application\Message\CommandMessage;

/** Identifies one effectful document export and its validated scope. @category UseCase */
final readonly class ExportInterventionReportDocumentCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $resourceId)
  {
  }
}
