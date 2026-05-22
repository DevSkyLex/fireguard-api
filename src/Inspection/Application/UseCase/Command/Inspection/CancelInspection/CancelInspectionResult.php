<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\CancelInspection;

use Shared\Application\Message\ResultMessage;

final readonly class CancelInspectionResult implements ResultMessage
{
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
  ) {
  }
}
