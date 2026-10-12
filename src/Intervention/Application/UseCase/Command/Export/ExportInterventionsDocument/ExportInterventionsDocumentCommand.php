<?php

declare(strict_types=1);

namespace Intervention\Application\UseCase\Command\Export\ExportInterventionsDocument;

use Shared\Application\Message\CommandMessage;

/** Identifies one effectful document export and its validated scope. @category UseCase */
final readonly class ExportInterventionsDocumentCommand implements CommandMessage
{
  /**
   * @param array<string, mixed> $filters validated export filters
   */
  public function __construct(public string $userId, public string $organizationId, public array $filters = [])
  {
  }
}
