<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\ExportInspectionReportDocument;

use Shared\Application\Message\CommandMessage;

/** Identifies one effectful document export and its validated scope. @category UseCase */
final readonly class ExportInspectionReportDocumentCommand implements CommandMessage
{
  public function __construct(public string $userId, public string $organizationId, public string $resourceId)
  {
  }
}
