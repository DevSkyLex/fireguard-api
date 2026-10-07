<?php

declare(strict_types=1);

namespace MaintenanceCost\Application\UseCase\Query\Currency\ReadMaintenanceCurrency;

use Shared\Application\Message\QueryMessage;

/** Permission-scoped private currency settings request. */
final readonly class ReadMaintenanceCurrencyQuery implements QueryMessage
{
  public function __construct(public string $organizationId, public string $actorUserId)
  {
  }
}
