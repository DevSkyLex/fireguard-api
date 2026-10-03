<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetFacilityPaths;

use Shared\Application\Message\QueryMessage;

/**
 * Query GetFacilityPathsQuery.
 *
 * @category UseCase
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetFacilityPathsQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization
   * @param list<string> $facilityIds the organization-scoped collection page
   * @param bool $includePath whether to enrich breadcrumbs alongside hierarchy diagnostics
   */
  public function __construct(public string $organizationId, public array $facilityIds, public bool $includePath = true)
  {
  }
  // #endregion
}
