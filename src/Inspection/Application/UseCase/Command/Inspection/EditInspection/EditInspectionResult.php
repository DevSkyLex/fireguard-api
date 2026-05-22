<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\EditInspection;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

final readonly class EditInspectionResult implements ResultMessage
{
  public function __construct(
    public string $inspectionId,
    public string $organizationId,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
