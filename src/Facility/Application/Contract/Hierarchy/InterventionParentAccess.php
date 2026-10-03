<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Hierarchy;

/**
 * Contract InterventionParentAccess.
 *
 * @category Contract
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionParentAccess: string
{
  case NOT_FOUND = 'not_found';
  case DENIED = 'denied';
  case GRANTED = 'granted';
}
