<?php

declare(strict_types=1);

namespace Procurement\Application\UseCase\Query\ReadProcurement;

use Shared\Application\Message\QueryMessage;

/** Scoped paged reads never mutate procurement or inventory. */
final readonly class ReadProcurementQuery implements QueryMessage
{
  public function __construct(public string $actorId, public string $organizationId, public string $action, public ?string $id = null, public string $search = '', public ?bool $archived = false, public ?string $status = null, public ?string $supplierId = null, public int $page = 1, public int $itemsPerPage = 30)
  {
  }
}
