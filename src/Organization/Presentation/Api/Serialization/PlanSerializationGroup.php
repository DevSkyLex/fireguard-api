<?php

declare(strict_types=1);

namespace Organization\Presentation\Api\Serialization;

/**
 * Serialization PlanSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class PlanSerializationGroup
{
  public const string READ = 'Plan:read';

  public const string WRITE = 'Plan:write';
}
