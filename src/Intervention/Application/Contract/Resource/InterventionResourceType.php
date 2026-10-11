<?php

declare(strict_types=1);

namespace Intervention\Application\Contract\Resource;

/** Public resource owner discriminator.
 * @category Contract
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
