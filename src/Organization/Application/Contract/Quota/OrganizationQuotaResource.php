<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Quota;

/**
 * Enum OrganizationQuotaResource.
 *
 * Enumerates the countable resources whose quantity a subscription plan may
 * cap, as named on the module's contract surface. Sibling modules pass these
 * cases to {@see \Organization\Application\Port\Inbound\OrganizationQuotaPort}
 * without importing the Domain enum of the same name;
 * `OrganizationQuotaService` maps each case back to its Domain counterpart
 * internally.
 *
 * @category Contract
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationQuotaResource: string
{
  case MEMBERS = 'members';
  case FACILITIES = 'facilities';
  case EQUIPMENT = 'equipment';
  case INSPECTIONS = 'inspections';
}
