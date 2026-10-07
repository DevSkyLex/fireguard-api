<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Contract\Cost;

use DateTimeImmutable;

/** Class MaintenanceExpense. An immutable external expense or motivated adjustment. @category Contract */
final readonly class MaintenanceExpense
{
  public function __construct(public string $id, public string $organizationId, public string $interventionId, public ?string $workItemId, public string $clientId, public string $amount, public string $currency, public string $description, public DateTimeImmutable $incurredAt, public ?string $adjustmentOf, public string $createdBy, public string $payloadHash, public DateTimeImmutable $createdAt)
  {
  }
}
