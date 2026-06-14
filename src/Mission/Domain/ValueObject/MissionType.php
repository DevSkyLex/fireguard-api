<?php

declare(strict_types=1);

namespace Mission\Domain\ValueObject;

/**
 * Enum MissionType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MissionType: string
{
  case SITE_SETUP = 'site_setup';
  case INVENTORY = 'inventory';
  case INSPECTION_CAMPAIGN = 'inspection_campaign';
}
