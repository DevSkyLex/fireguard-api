<?php

declare(strict_types=1);

namespace Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteCanonicalFacilityResult.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable:
 * a childless scratchpad row is `hardDeleted`, a published one is `archived`,
 * and a repeat DELETE on an already-archived facility is an idempotent no-op
 * — neither flag set, no revision bump, no ledger row.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalFacilityResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $facilityId,
    public bool $hardDeleted = false,
    public bool $archived = false,
  ) {
  }
  // #endregion
}
