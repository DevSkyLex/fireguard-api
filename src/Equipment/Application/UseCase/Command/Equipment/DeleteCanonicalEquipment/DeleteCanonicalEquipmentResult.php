<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase DeleteCanonicalEquipmentResult.
 *
 * The canonical DELETE has three outcomes and they are not interchangeable:
 * a scratchpad row is `hardDeleted`, a published one is decommissioned (with
 * `previousStatus` set), and a repeat DELETE on an already-decommissioned
 * asset is an idempotent no-op — neither flag set, no revision bump, no
 * maintenance-log sync, no ledger row.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class DeleteCanonicalEquipmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $equipmentId,
    public bool $hardDeleted = false,
    public ?string $previousStatus = null,
  ) {
  }
  // #endregion
}
