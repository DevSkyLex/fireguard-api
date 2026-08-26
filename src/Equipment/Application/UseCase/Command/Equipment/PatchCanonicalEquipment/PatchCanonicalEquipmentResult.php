<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment;

use Shared\Application\Message\ResultMessage;

/**
 * UseCase PatchCanonicalEquipmentResult.
 *
 * `previousStatus` is non-null exactly when a PUBLISHED record's status
 * moved — the condition that syncs the maintenance log and produces an audit
 * event. The processor does not read it; it is asserted by the handler tests
 * and kept because a Result that hides why an event fired is a Result nobody
 * can test.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PatchCanonicalEquipmentResult implements ResultMessage
{
  // #region Constructor
  public function __construct(
    public string $equipmentId,
    public string $status,
    public int $revision,
    public ?string $previousStatus = null,
  ) {
  }
  // #endregion
}
