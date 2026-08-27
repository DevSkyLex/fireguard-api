<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

/**
 * Exception OrganizationArchivedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationArchivedException extends RuntimeException
{
  // #region Methods
  /**
   * Method cannotSuspend.
   *
   * Creates an exception for a suspension attempt on an archived organization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotSuspend(): self
  {
    return new self('An archived organization cannot be suspended; restore it first (isActive: true).');
  }

  /**
   * Method cannotTransferOwnership.
   *
   * Creates an exception for an ownership transfer attempt on an archived organization.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function cannotTransferOwnership(): self
  {
    return new self('An archived organization\'s ownership cannot be transferred; restore it first (isActive: true).');
  }

  /**
   * Method cannotReactivateMember.
   *
   * Creates an exception for a member reactivation attempt on an archived organization.
   *
   * @since 1.1.0
   *
   * @return self the exception instance
   */
  public static function cannotReactivateMember(): self
  {
    return new self('A member cannot be reactivated in an archived organization; restore it first (isActive: true).');
  }

  /**
   * Method cannotRemoveLogo.
   *
   * Creates an exception for a logo removal attempt on an archived organization.
   *
   * @since 1.2.0
   *
   * @return self the exception instance
   */
  public static function cannotRemoveLogo(): self
  {
    return new self('An archived organization\'s logo cannot be removed; restore it first (isActive: true).');
  }
  // #endregion
}
