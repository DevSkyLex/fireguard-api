<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Serialization;

/**
 * Serialization PlanSerializationGroup.
 *
 * @category Serialization
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanSerializationGroup
{
  /**
   * Constant READ
   */
  public const string READ = 'Plan:read';

  /**
   * Constant WRITE
   */
  public const string WRITE = 'Plan:write';
}
