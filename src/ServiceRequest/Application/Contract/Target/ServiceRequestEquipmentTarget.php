<?php

declare(strict_types=1);

namespace ServiceRequest\Application\Contract\Target;

/**
 * Class ServiceRequestEquipmentTarget
 *
 * Exposes published equipment identity to a repair request without persistence models.
 *
 * @category Contract
 */
final readonly class ServiceRequestEquipmentTarget
{
  public function __construct(public string $id, public ?string $name, public ?string $assetCode, public string $status, public ?string $facilityId)
  {
  }
}
