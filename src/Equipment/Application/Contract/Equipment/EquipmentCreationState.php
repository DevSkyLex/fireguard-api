<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Equipment;

use DateTimeImmutable;

/**
 * Contract EquipmentCreationState.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class EquipmentCreationState
{
  // #region Constructor
  /**
   * @since 1.0.0
   *
   * @param ?string $interventionId the current intervention
   * @param string $recordStatus the current publication state
   * @param int $revision the durable resource revision
   * @param DateTimeImmutable $updatedAt the persisted update time
   */
  public function __construct(public ?string $interventionId, public string $recordStatus, public int $revision, public DateTimeImmutable $updatedAt)
  {
  }
  // #endregion
}
