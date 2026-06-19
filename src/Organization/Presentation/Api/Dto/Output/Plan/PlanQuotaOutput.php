<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Dto\Output\Plan;

use ApiPlatform\Metadata\ApiProperty;
use Organization\Presentation\Api\Serialization\PlanSerializationGroup;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * DTO PlanQuotaOutput.
 *
 * One per-resource allowance descriptor of a plan: the raw cap plus a ready-made
 * human sentence (for example "Up to 125 facilities" / "Unlimited inspections")
 * so the client can render rich plan cards without re-deriving the wording.
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanQuotaOutput
{
  // #region Properties
  /**
   * Property resource.
   *
   * The quota resource key (members, facilities, equipment, inspections).
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $resource = '';

  /**
   * Property label.
   *
   * The human-readable resource label.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $label = '';

  /**
   * Property limit.
   *
   * The per-resource cap, or null when the plan grants unlimited usage.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public ?int $limit = null;

  /**
   * Property summary.
   *
   * The allowance phrased as a sentence ready for display.
   *
   * @since 1.0.0
   */
  #[Groups([PlanSerializationGroup::READ])]
  #[ApiProperty(readable: true, writable: false)]
  public string $summary = '';
  // #endregion
}
