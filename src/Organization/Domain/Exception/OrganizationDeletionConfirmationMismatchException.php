<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationDeletionConfirmationMismatchException.
 *
 * Raised when the archive (delete) request's typed slug confirmation is
 * missing or does not match the organization's current slug. This guards
 * against accidental clicks; it never implies the organization itself is in
 * an invalid state.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationDeletionConfirmationMismatchException extends RuntimeException
{
  // #region Methods
  /**
   * Method missing.
   *
   * Creates an exception for a deletion request with no slug confirmation.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function missing(): self
  {
    return new self('Deletion confirmation is required: pass the organization slug as the "slug" query parameter.');
  }

  /**
   * Method mismatched.
   *
   * Creates an exception for a deletion request whose slug confirmation does
   * not match the organization's current slug.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function mismatched(): self
  {
    return new self('Deletion confirmation does not match the organization slug.');
  }
  // #endregion
}
