<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use DateTimeImmutable;
use Shared\Application\Message\ResultMessage;

final readonly class GetNonConformityResult implements ResultMessage
{
  public function __construct(
    public string $nonConformityId,
    public string $inspectionId,
    public string $description,
    public string $severity,
    public string $status,
    public ?string $dueAt,
    public ?string $resolvedAt,
    public ?string $notes,
    public DateTimeImmutable $createdAt,
    public DateTimeImmutable $updatedAt,
  ) {
  }
}
