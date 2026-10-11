<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListCanonicalFacilities;

use Facility\Application\Contract\Facility\CanonicalFacilityPage;
use Shared\Application\Message\ResultMessage;

/**
 * Result ListCanonicalFacilitiesResult.
 *
 * Carries the authorized page and batched ancestry enrichment.
 *
 * @category Result
 */
final readonly class ListCanonicalFacilitiesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct.
   *
   * @param array<string, list<array{id: string, name: string, type: string}>> $paths the batched paths
   * @param array<string, list<string>> $hierarchyIssues the batched diagnostics
   */
  public function __construct(
    public CanonicalFacilityPage $page,
    public int $pageNumber,
    public int $itemsPerPage,
    public array $paths,
    public array $hierarchyIssues,
  ) {
  }
  // #endregion
}
