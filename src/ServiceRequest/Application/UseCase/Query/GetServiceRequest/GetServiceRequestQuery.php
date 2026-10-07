<?php

declare(strict_types=1);

namespace ServiceRequest\Application\UseCase\Query\GetServiceRequest;

use Shared\Application\Message\QueryMessage;

/** Class GetServiceRequestQuery. Reads retained request history within one organization. @category Query */
final readonly class GetServiceRequestQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $requestId)
  {
  }
}
