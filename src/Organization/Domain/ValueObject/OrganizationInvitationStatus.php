<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationInvitationStatus.
 *
 * @category ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationInvitationStatus: string
{
  /**
   * Case PENDING
   */
  case PENDING = 'pending';

  /**
   * Case ACCEPTED
   */
  case ACCEPTED = 'accepted';

  /**
   * Case REVOKED
   */
  case REVOKED = 'revoked';

  /**
   * Case EXPIRED
   */
  case EXPIRED = 'expired';

  // #region Methods
  /**
   * Method isPending.
   *
   * @since 1.0.0
   */
  public function isPending(): bool
  {
    return self::PENDING === $this;
  }
  // #endregion
}
