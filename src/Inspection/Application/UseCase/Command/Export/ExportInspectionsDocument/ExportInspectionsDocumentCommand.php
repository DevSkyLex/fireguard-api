<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Export\ExportInspectionsDocument;

use Shared\Application\Message\CommandMessage;

/** Identifies one effectful document export and its validated scope. @category UseCase */
final readonly class ExportInspectionsDocumentCommand implements CommandMessage
{
  /**
   * @param array<string, mixed> $filters validated export filters
   */
  public function __construct(public string $userId, public string $organizationId, public array $filters = [])
  {
  }
}
