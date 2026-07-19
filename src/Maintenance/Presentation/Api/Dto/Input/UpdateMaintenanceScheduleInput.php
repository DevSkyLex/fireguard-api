<?php

declare(strict_types=1);

namespace Maintenance\Presentation\Api\Dto\Input;

use ApiPlatform\Metadata\ApiProperty;
use Maintenance\Presentation\Api\Validator\ValidPeriodicityOverride\ValidPeriodicityOverride;

/**
 * DTO UpdateMaintenanceScheduleInput.
 *
 * `PATCH /api/maintenance/schedules/{id}` merge-patch body: only
 * `intervalOverride` is writable. A present `null` clears the override
 * (reverting to the organization's compliance periodicity for the
 * equipment type).
 *
 * @category DTO
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class UpdateMaintenanceScheduleInput
{
  /**
   * Property intervalOverride.
   *
   * @since 1.0.0
   */
  #[ValidPeriodicityOverride]
  #[ApiProperty(example: 'P90D')]
  public ?string $intervalOverride = null;
}
