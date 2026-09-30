<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionResourceType.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionResourceType: string
{
  /**
   * Case FACILITY
   */
  case FACILITY = 'facility';

  /**
   * Case EQUIPMENT
   */
  case EQUIPMENT = 'equipment';

  /**
   * Case INSPECTION
   */
  case INSPECTION = 'inspection';
}
