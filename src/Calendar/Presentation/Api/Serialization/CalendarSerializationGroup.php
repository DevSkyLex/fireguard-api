<?php

declare(strict_types=1);

namespace Calendar\Presentation\Api\Serialization;

/**
 * Serialization CalendarSerializationGroup.
 *
 * @category Serialization
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class CalendarSerializationGroup
{
  public const string READ = 'Calendar:read';

  public const string WRITE = 'Calendar:write';
}
