<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Export\GenerateEquipmentExport;

use Shared\Application\Message\CommandMessage;

/** Declares one authorized artifact generation; the HTTP operation selects the fixed format. @category UseCase */
final readonly class GenerateEquipmentExportCommand implements CommandMessage
{
  /**
   * @param list<string>|null $equipmentIds original label selection, ordered as supplied
   */
  public function __construct(public string $userId, public string $organizationId, public string $format, public ?string $equipmentId = null, public ?array $equipmentIds = null, public ?string $facilityId = null)
  {
  }
}
