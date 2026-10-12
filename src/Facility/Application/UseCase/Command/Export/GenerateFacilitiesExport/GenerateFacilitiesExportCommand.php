<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Export\GenerateFacilitiesExport;

use Shared\Application\Message\CommandMessage;

/** Captures the controller-normalized original facility export selection. @category UseCase */
final readonly class GenerateFacilitiesExportCommand implements CommandMessage
{
  /**
   * @param array<string, mixed> $filters original validated filter values
   */
  public function __construct(public string $userId, public string $organizationId, public array $filters = [])
  {
  }
}
