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
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum ProvisionOutcome
{
  /**
   * Case CREATED
   */
  case CREATED;

  /**
   * Case QUOTA_EXCEEDED
   */
  case QUOTA_EXCEEDED;

  /**
   * Case ALREADY_MEMBER
   */
  case ALREADY_MEMBER;

  /**
   * Case ALREADY_INVITED
   */
  case ALREADY_INVITED;

  /**
   * Case UNKNOWN_ROLE
   */
  case UNKNOWN_ROLE;

  /**
   * Case INVALID
   */
  case INVALID;
}
