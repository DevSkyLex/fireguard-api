<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\ListCanonicalFacilities;

use Shared\Application\Message\QueryMessage;

/**
 * Query ListCanonicalFacilitiesQuery.
 *
 * Carries HTTP filters as transport-independent identifiers.
 *
 * @category Query
 */
final readonly class ListCanonicalFacilitiesQuery implements QueryMessage
{
  // #region Constructor
  /**
   * Method __construct.
   */
  public function __construct(
    public string $userId,
    public ?string $organizationId = null,
    public ?string $interventionId = null,
    public ?string $recordStatus = null,
    public int $page = 1,
    public int $itemsPerPage = 50,
    public bool $includePath = false,
  ) {
  }
  // #endregion
}
