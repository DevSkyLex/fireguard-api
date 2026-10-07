<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Intervention;

use DateTimeImmutable;

/**
 * Contract ServicedEquipmentEntry.
 *
 * Read model exposed by {@see \Equipment\Application\Port\Outbound\InterventionServiceReportPort},
 * describing one published equipment item mutated by an applied intervention
 * change, without depending on the Intervention module's Domain or
 * Infrastructure layers.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ServicedEquipmentEntry
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $equipmentId the serviced equipment identifier
   * @param string $action the work item action, or a derived label when no work item is linked
   * @param string $changeToken the applied change's identifier, used as the dedup idempotency token
   * @param ?string $workItemId the linked work item identifier, when set
   * @param ?DateTimeImmutable $performedAt the validated field-work date, when available
   * @param ?string $notes the recorded work description, when available
   * @param ?string $authorId the executing actor, when available
   */
  public function __construct(
    public string $equipmentId,
    public string $action,
    public string $changeToken,
    public ?string $workItemId,
    public ?DateTimeImmutable $performedAt = null,
    public ?string $notes = null,
    public ?string $authorId = null,
  ) {
  }
  // #endregion
}
