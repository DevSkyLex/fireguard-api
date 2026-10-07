<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\Port\Inbound;

/** Interface MaintenanceCostPublicationPort. Atomically fences stock declarations and freezes private costs. @category Port */
interface MaintenanceCostPublicationPort
{
  public function assertReadyToPublish(string $organizationId, string $interventionId): void;

  public function freeze(string $organizationId, string $interventionId, string $publicationId, int $interventionRevision): void;
}
