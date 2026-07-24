<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionPriority.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionPriority: string
{
  case LOW = 'low';
  case NORMAL = 'normal';
  case HIGH = 'high';
  case URGENT = 'urgent';
}
