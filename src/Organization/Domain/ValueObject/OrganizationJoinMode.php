<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * ValueObject OrganizationJoinMode.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationJoinMode: string
{
  /**
   * Case INVITATION_ONLY
   */
  case INVITATION_ONLY = 'invitation_only';

  /**
   * Case APPROVAL_REQUIRED
   */
  case APPROVAL_REQUIRED = 'approval_required';

  /**
   * Case AUTOMATIC
   */
  case AUTOMATIC = 'automatic';
}
