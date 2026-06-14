<?php

declare(strict_types=1);

namespace Mission\Domain\ValueObject;

/**
 * Enum MissionResourceType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MissionResourceType: string
{
  case FACILITY = 'facility';
  case EQUIPMENT = 'equipment';
  case INSPECTION = 'inspection';
}
