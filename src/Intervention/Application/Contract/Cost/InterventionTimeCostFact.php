<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Cost;

use DateTimeImmutable;

/** Class InterventionTimeCostFact. Independent journal fact without any finance fields. @category Contract */
final readonly class InterventionTimeCostFact
{
  public function __construct(public string $id, public string $workItemId, public string $memberId, public string $workedOn, public int $minutes, public int $revision, public bool $cancelled, public ?string $note, public DateTimeImmutable $updatedAt)
  {
  }
}
