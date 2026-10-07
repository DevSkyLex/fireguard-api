<?php

declare(strict_types=1);

namespace Maintenance\Application\UseCase\Query\Plan\ReadMaintenancePlans;

use Shared\Application\Message\QueryMessage;

/** Reads a plan, preview, engine state or a bounded plan page. */
final readonly class ReadMaintenancePlansQuery implements QueryMessage
{
  public function __construct(
    public string $organizationId,
    public string $actorUserId,
    public string $action = 'list',
    public ?string $planId = null,
    public int $page = 1,
    public int $itemsPerPage = 30,
    public ?string $equipmentId = null,
    public ?string $operationKind = null,
    public ?string $search = null,
  ) {
  }
}
