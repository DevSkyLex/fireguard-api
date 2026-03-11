<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListInspections;

use Shared\Application\Contract\Pagination\Pagination;
use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListInspectionsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListInspectionsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $equipmentId = null,
    public ?string $facilityId = null,
    public ?string $result = null,
    public ?string $status = null,
    public Pagination $pagination = new Pagination(),
  ) {
  }
  // #endregion
}
