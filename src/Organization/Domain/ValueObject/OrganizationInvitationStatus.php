<?php

declare(strict_types=1);

namespace Organization\Domain\ValueObject;

/**
 * Enum OrganizationInvitationStatus.
 *
 * @category ValueObject
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
enum OrganizationInvitationStatus: string
{
  case PENDING = 'pending';
  case ACCEPTED = 'accepted';
  case REVOKED = 'revoked';
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
