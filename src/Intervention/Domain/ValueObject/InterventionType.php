<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionType.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionType: string
{
  /**
   * Case SITE_SETUP
   */
  case SITE_SETUP = 'site_setup';

  /**
   * Case INVENTORY
   */
  case INVENTORY = 'inventory';

  /**
   * Case INSPECTION_CAMPAIGN
   */
  case INSPECTION_CAMPAIGN = 'inspection_campaign';

  /** Case PREVENTIVE_MAINTENANCE. */
  case PREVENTIVE_MAINTENANCE = 'preventive_maintenance';

  /** Case CORRECTIVE_MAINTENANCE. */
  case CORRECTIVE_MAINTENANCE = 'corrective_maintenance';
}
