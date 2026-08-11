<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationOwnerCannotLeaveException.
 *
 * Raised when the organization's current owner attempts to leave the
 * organization through the member self-removal flow. The owner cannot leave
 * without first transferring ownership to another active member, since
 * leaving would otherwise strip the organization of its owner entirely.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationOwnerCannotLeaveException extends RuntimeException
{
  // #region Methods
  /**
   * Method mustTransferOwnershipFirst.
   *
   * Creates an exception for an owner attempting to leave the organization
   * without first transferring ownership.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function mustTransferOwnershipFirst(): self
  {
    return new self('The organization owner cannot leave; transfer ownership to another member first.');
  }
  // #endregion
}
