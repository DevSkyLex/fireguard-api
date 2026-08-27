<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Query\Inspection\ListCanonicalInspections;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListCanonicalInspectionsQuery.
 *
 * `organizationId` is required and already resolved — the caller ran
 * `ResolveCanonicalInspectionScopeQuery` and permission-checked the answer
 * before asking for rows. `recordStatus` is nullable so the handler can apply
 * the endpoint's default, which depends on whether an intervention was named.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListCanonicalInspectionsQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $organizationId,
    public ?string $interventionId = null,
    public ?string $equipmentId = null,
    public ?string $recordStatus = null,
    public int $page = 1,
    public int $itemsPerPage = 50,
  ) {
  }
  // #endregion
}
