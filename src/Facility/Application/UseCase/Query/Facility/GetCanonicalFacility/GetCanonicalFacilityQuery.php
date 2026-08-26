<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Query\Facility\GetCanonicalFacility;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase GetCanonicalFacilityQuery.
 *
 * Deliberately unscoped by organization: the canonical routes carry no
 * organization segment, so the organization to check against is the one on
 * the row itself. The permission gate that follows the read is what makes it
 * safe, and it answers 404 for a row outside the caller's scope.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetCanonicalFacilityQuery implements QueryMessage
{
  // #region Constructor
  public function __construct(
    public string $facilityId,
  ) {
  }
  // #endregion
}
