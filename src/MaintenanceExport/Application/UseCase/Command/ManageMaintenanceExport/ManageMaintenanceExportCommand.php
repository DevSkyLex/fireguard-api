<?php

declare(strict_types=1);

namespace MaintenanceExport\Application\UseCase\Command\ManageMaintenanceExport;

use Shared\Application\Message\CommandMessage;

/**
 * Class ManageMaintenanceExportCommand
 * Carries authenticated primitives into the single write entry point.
 *
 * @category Command
 */
final readonly class ManageMaintenanceExportCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @param array<string,mixed> $payload validated transport declarations
   *
   * @return void
   */
  public function __construct(public string $actorId, public string $organizationId, public string $action, public ?string $id, public ?int $expectedRevision, public array $payload)
  {
  }
  // #endregion
}
