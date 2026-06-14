<?php

declare(strict_types=1);

namespace Mission\Domain\ValueObject;

/**
 * Enum MissionPriority.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum MissionPriority: string
{
  case LOW = 'low';
  case NORMAL = 'normal';
  case HIGH = 'high';
  case URGENT = 'urgent';
}
