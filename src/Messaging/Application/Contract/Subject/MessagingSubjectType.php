<?php

declare(strict_types=1);

namespace Messaging\Application\Contract\Subject;

/**
 * Enum MessagingSubjectType
 *
 * Stable subject vocabulary consumed by owner-published resolver adapters.
 *
 * @category Contract
 */
enum MessagingSubjectType: string
{
  case FACILITY = 'facility';
  case EQUIPMENT = 'equipment';
  case INTERVENTION = 'intervention';
  case NON_CONFORMITY = 'non_conformity';
  case CHANNEL = 'channel';
  case DIRECT = 'direct';
}
