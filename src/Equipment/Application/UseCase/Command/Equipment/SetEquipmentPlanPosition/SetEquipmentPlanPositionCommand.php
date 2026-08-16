<?php

declare(strict_types=1);

namespace Equipment\Application\UseCase\Command\Equipment\SetEquipmentPlanPosition;

use Shared\Application\Message\CommandMessage;

/**
 * UseCase SetEquipmentPlanPositionCommand.
 *
 * `attachmentId`, `x` and `y` travel together: all three `null` clears the
 * equipment's plan position, all three set assign it. A caller providing
 * only some of the three is a handler-level validation error (400), not a
 * partial update — mirrors Facility's `SetFacilityPlanGeometryCommand`.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class SetEquipmentPlanPositionCommand implements CommandMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the organization identifier
   * @param string $equipmentId the target equipment identifier
   * @param ?string $attachmentId the floor plan attachment identifier, or null to clear
   * @param ?float $x the normalized x coordinate, or null to clear
   * @param ?float $y the normalized y coordinate, or null to clear
   */
  public function __construct(
    public string $organizationId,
    public string $equipmentId,
    public ?string $attachmentId,
    public ?float $x,
    public ?float $y,
  ) {
  }
  // #endregion
}
