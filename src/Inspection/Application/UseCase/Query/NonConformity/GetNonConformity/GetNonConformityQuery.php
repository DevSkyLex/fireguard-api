<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\NonConformity\GetNonConformity;

use Shared\Application\Message\QueryMessage;

final readonly class GetNonConformityQuery implements QueryMessage
{
  public function __construct(
    public string $organizationId,
    public string $inspectionId,
    public string $nonConformityId,
  ) {
  }
}
