<?php

declare(strict_types=1);

namespace Intervention\Domain\ValueObject;

/**
 * Enum InterventionPriority.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum InterventionPriority: string
{
  /**
   * Case LOW
   */
  case LOW = 'low';

  /**
   * Case NORMAL
   */
  case NORMAL = 'normal';

  /**
   * Case HIGH
   */
  case HIGH = 'high';

  /**
   * Case URGENT
   */
  case URGENT = 'urgent';
}
