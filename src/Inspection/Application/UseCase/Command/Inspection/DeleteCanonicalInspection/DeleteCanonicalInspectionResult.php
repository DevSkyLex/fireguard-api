<?php

declare(strict_types=1);

namespace Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteCanonicalInspectionResult.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable:
 * a scratchpad row is `hardDeleted`, a published one is cancelled (with
 * `previousStatus` set), and a repeat DELETE on an already-cancelled row is
 * an idempotent no-op — neither flag set, no revision bump, no ledger row.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalInspectionResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $inspectionId,
    public bool $hardDeleted = false,
    public ?string $previousStatus = null,
  ) {
  }
  // #endregion
}
