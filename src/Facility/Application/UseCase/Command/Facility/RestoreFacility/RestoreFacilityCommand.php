<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\RestoreFacility;

use Shared\Application\Message\CommandMessage;

final readonly class RestoreFacilityCommand implements CommandMessage
{
  public function __construct(
    public string $organizationId,
    public string $facilityId,
  ) {
  }
}
