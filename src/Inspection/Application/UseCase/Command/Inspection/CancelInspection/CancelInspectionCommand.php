<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Shared\Application\Message\CommandMessage;

final readonly class CancelInspectionCommand implements CommandMessage
{
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
  ) {
  }
}
