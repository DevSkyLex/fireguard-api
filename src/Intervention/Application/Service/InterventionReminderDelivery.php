<?php

declare(strict_types=1);

namespace Intervention\Application\Service;

use DateTimeImmutable;

/** The intervention and recipients of one due-date notification. */
final readonly class InterventionReminderDelivery
{
  /**
   * @param list<string> $memberIds
   */
  public function __construct(
    public string $interventionId,
    public int $interventionNumber,
    public string $interventionName,
    public string $organizationId,
    public DateTimeImmutable $dueAt,
    public array $memberIds,
  ) {
  }
}
