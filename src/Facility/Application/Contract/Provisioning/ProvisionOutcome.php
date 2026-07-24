<?php

declare(strict_types=1);

namespace Facility\Application\Contract\Provisioning;

/**
 * Enum ProvisionOutcome.
 *
 * The outcome of one programmatic facility provisioning attempt through
 * {@see \Facility\Application\Port\Inbound\FacilityProvisioningPort}. A
 * self-contained twin of `Equipment\Application\Contract\Provisioning\ProvisionOutcome`
 * (same values, independent type): the two sibling modules do not depend on
 * each other's contracts.
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
