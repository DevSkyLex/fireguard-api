<?php

declare(strict_types=1);

namespace Equipment\Application\Contract\Provisioning;

/**
 * Enum ProvisionOutcome.
 *
 * The outcome of one programmatic equipment provisioning attempt through
 * {@see \Equipment\Application\Port\Inbound\EquipmentProvisioningPort}.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ProvisionOutcome
{
  case CREATED;
  case QUOTA_EXCEEDED;
  case INVALID;
}
