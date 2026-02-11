<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationInvitationNotFoundException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationInvitationNotFoundException extends RuntimeException
{
  // #region Methods
  /**
   * Method withId.
   *
   * Creates an exception for a missing invitation identifier.
   *
   * @since 1.0.0
   *
   * @param string $id the invitation identifier
   *
   * @return self the exception instance
   */
  public static function withId(string $id): self
  {
    return new self(sprintf('Organization invitation with ID "%s" not found.', $id));
  }

  /**
   * Method withToken.
   *
   * Creates an exception for a missing invitation token.
   *
   * @since 1.0.0
   */
  public static function withToken(): self
  {
    return new self('Organization invitation token is invalid or no longer available.');
  }
  // #endregion
}
