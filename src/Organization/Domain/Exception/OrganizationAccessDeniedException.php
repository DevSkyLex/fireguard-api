<?php

declare(strict_types=1);

namespace Organization\Domain\Exception;

use RuntimeException;

use function sprintf;

/**
 * Exception OrganizationAccessDeniedException.
 *
 * @category Exception
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationAccessDeniedException extends RuntimeException
{
  /**
   * Method organizationSuspended.
   *
   * Creates an exception for a write attempted against a suspended
   * organization. Distinct from {@see self::missingPermission()} on purpose:
   * the caller holds the permission, the organization's state is what refuses
   * it, and telling them "missing permission" would send them looking at their
   * role.
   *
   * @since 1.2.0
   *
   * @param string $permission the permission that was refused
   *
   * @return self the exception instance
   */
  public static function organizationSuspended(string $permission, bool $archived = false): self
  {
    // The two states differ in the way out, so the message must too: a
    // suspended organization is restored by anyone holding
    // `organization.settings.write`, an archived one only by a platform
    // administrator. Telling an archived organization's members to restore it
    // would be advice they cannot act on.
    return new self(sprintf(
      $archived
        ? 'This organization is archived and is read-only; "%s" is unavailable, and only a platform administrator can reopen it.'
        : 'This organization is suspended and is read-only; "%s" is unavailable until it is restored.',
      $permission,
    ));
  }

  /**
   * Method missingPermission.
   *
   * Creates an exception for a missing organization permission.
   *
   * @since 1.0.0
   *
   * @param string $permission the required permission name
   *
   * @return self the exception instance
   */
  public static function missingPermission(string $permission): self
  {
    return new self(sprintf('Missing %s permission.', $permission));
  }

  /**
   * Method cannotGrantPermission.
   *
   * Creates an exception when a caller attempts to grant or assign a
   * permission they do not themselves hold (privilege-escalation guard).
   *
   * @since 1.0.0
   *
   * @param string $permission the permission the caller tried to grant
   *
   * @return self the exception instance
   */
  public static function cannotGrantPermission(string $permission): self
  {
    return new self(sprintf('Cannot grant permission "%s" that you do not hold.', $permission));
  }

  /**
   * Method ownershipTransferRequiresCurrentOwner.
   *
   * Creates an exception for an ownership transfer attempted by someone other
   * than the organization's current owner. This check is intentionally
   * independent from RBAC permissions: even a caller holding every
   * organization.* permission cannot transfer ownership on the owner's
   * behalf.
   *
   * @since 1.0.0
   *
   * @return self the exception instance
   */
  public static function ownershipTransferRequiresCurrentOwner(): self
  {
    return new self('Only the organization\'s current owner can transfer ownership.');
  }
}
