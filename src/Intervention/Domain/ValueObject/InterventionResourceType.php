<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionResourceType.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionResourceType: string
{
  case FACILITY = 'facility';
  case EQUIPMENT = 'equipment';
  case INSPECTION = 'inspection';
}
