<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationInvitationNotificationFailedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationNotificationFailedException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for an invitation whose notification email could not
   * be delivered.
   *
   * @since 1.0.0
   *
   * @param string $id the invitation identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf(
      'Organization invitation "%s" was revoked because its notification email could not be delivered.',
      $id,
    ));
  }
  // #endregion
}
