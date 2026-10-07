<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract\Work;

/**
 * Class ServiceRequestWorkRequest
 *
 * Carries one qualified repair conversion through the intervention owner's boundary.
 *
 * @category Contract
 */
final readonly class ServiceRequestWorkRequest
{
  public function __construct(
    public string $organizationId,
    public string $requestId,
    public string $actorId,
    public ?string $equipmentId,
    public ?string $siteId,
    public string $title,
    public string $description,
    public ?string $existingInterventionId,
    public ?string $existingTaskId,
    public string $clientOperationId,
  ) {
  }
}
