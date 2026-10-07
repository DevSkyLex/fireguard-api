<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Cost\GetMaintenanceCost;

use Shared\Application\Message\QueryMessage;

/** Class GetMaintenanceCostQuery. Authorized private intervention finance read. @category Query */
final readonly class GetMaintenanceCostQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $interventionId)
  {
  }
}
