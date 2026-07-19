<?php

declare(strict_types=1);

namespace Equipment\Presentation\Api\Dto\Output\Equipment;

use ApiPlatform\Metadata\ApiProperty;
use Equipment\Presentation\Api\Serialization\EquipmentSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO EquipmentKpiOutput.
 *
 * The four headline counters shown together on the equipment overview page.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class EquipmentKpiOutput
{
  // #region Properties
  /**
   * Property totalAssets.
   *
   * Total equipment count for the organization, every status included
   * (decommissioned equipment stays a recorded asset).
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $totalAssets = 0;

  /**
   * Property compliant.
   *
   * Equipment count whose cross-module maintenance due status (resolved
   * through `Equipment\Application\Port\Outbound\MaintenanceDueStatusPort`,
   * see L2.10) is `up_to_date`.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $compliant = 0;

  /**
   * Property dueSoon.
   *
   * Equipment count whose cross-module maintenance due status is `due_soon`.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $dueSoon = 0;

  /**
   * Property openNonConformities.
   *
   * **Not equipment-scoped**: non-conformities attach to inspections, not to
   * equipment (see `src/Inspection/MODULE.md`), so there is no reliable
   * per-equipment "open non-conformity" count the way there is a
   * per-equipment maintenance due status. This counts every non-conformity
   * currently `open` or `in_progress` across the WHOLE organization —
   * identical in scope to the equivalent counters already surfaced by the
   * Organization dashboard and the Compliance register.
   *
   * @since 1.0.0
   */
  #[Groups([EquipmentSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public int $openNonConformities = 0;
  // #endregion
}
