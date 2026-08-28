<?php

declare(strict_types=1);

namespace Organization\Application\Contract\Provisioning;

/**
 * Enum ProvisionOutcome.
 *
 * The outcome of one programmatic member-invitation provisioning attempt
 * through {@see \Organization\Application\Port\Inbound\MemberInvitationProvisioningPort}.
 * A self-contained sibling of `Equipment\Application\Contract\Provisioning\ProvisionOutcome`
 * and `Facility\...\ProvisionOutcome` (independent type, per the convention
 * that provisioning modules never depend on each other's contracts), with
 * three extra cases the invitation flow needs so a bulk import can report
 * each failure distinctly: an address already holding an active membership,
 * an address already holding a pending invitation, and an unknown role name.
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
  case ALREADY_MEMBER;
  case ALREADY_INVITED;
  case UNKNOWN_ROLE;
  case INVALID;
}
