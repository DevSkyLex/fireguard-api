<?php

declare(strict_types=1);

namespace Authorization\Domain\ValueObject;

use Shared\Domain\Exception\InvalidValueException;
use Stringable;

use function sprintf;
use function preg_match;
use function explode;
use function count;

/**
 * ValueObject PermissionName
 * @final
 *
 * Represents a permission name in the RBAC system.
 * Format: resource.action (e.g., users.create, clients.read)
 * Supports wildcards: users.* (all actions on users), *.* (all permissions)
 *
 * @category ValueObject
 * @package Authorization\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PermissionName implements Stringable
{
  //#region Constants
  /**
   * Constant PATTERN
   * 
   * Pattern for permission name
   * 
   * @access private
   * @since 1.0.0
   * 
   * @var string
   */
  private const string PATTERN = '/^([a-z_]+|\*)(\.([a-z_]+|\*))?$/';
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the 
   * PermissionName class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param string $value The permission name value.
   *
   * @throws InvalidValueException If the permission name is invalid.
   */
  public function __construct(public string $value)
  {
    if ($value === '') {
      throw InvalidValueException::because(
        message: 'Permission name cannot be empty.'
      );
    }

    if (!preg_match(pattern: self::PATTERN, subject: $value)) {
      throw InvalidValueException::because(
        message: sprintf(
          'Invalid permission name "%s". Must be in format "resource.action" (e.g., users.create, clients.*, *.*)',
          $value
        )
      );
    }
  }
  //#endregion

  //#region Methods
  /**
   * Method matches
   *
   * Checks if this permission matches the required permission.
   * Supports wildcard matching.
   *
   * Examples:
   * - users.create matches users.create (exact match)
   * - users.* matches users.create (wildcard action)
   * - *.* matches users.create (wildcard all)
   * - users.create does NOT match users.delete
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $required The required permission to check against.
   *
   * @return bool True if this permission matches the required permission.
   */
  public function matches(self $required): bool
  {
    // Exact match
    if ($this->value === $required->value) {
      return true;
    }

    // Super admin wildcard (*.*)
    if ($this->value === '*.*') {
      return true;
    }

    $thisParts = explode('.', $this->value);
    $requiredParts = explode('.', $required->value);

    // Handle single-part permissions (e.g., "admin" without action)
    if (count($thisParts) === 1 || count($requiredParts) === 1) {
      return $thisParts[0] === '*' || $thisParts[0] === $requiredParts[0];
    }

    // Resource wildcard (e.g., users.* matches users.create)
    if ($thisParts[0] === $requiredParts[0] && $thisParts[1] === '*') {
      return true;
    }

    // Action wildcard for all resources (e.g., *.read matches users.read)
    if ($thisParts[0] === '*' && $thisParts[1] === $requiredParts[1]) {
      return true;
    }

    return false;
  }

  /**
   * Method resource
   *
   * Returns the resource part of the permission.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The resource (e.g., "users" from "users.create").
   */
  public function resource(): string
  {
    $parts = explode('.', $this->value);
    return $parts[0];
  }

  /**
   * Method action
   *
   * Returns the action part of the permission.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string|null The action (e.g., "create" from "users.create"), or null if no action.
   */
  public function action(): ?string
  {
    $parts = explode('.', $this->value);
    return $parts[1] ?? null;
  }

  /**
   * Method equals
   *
   * Compares two PermissionName objects 
   * for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other PermissionName object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->value === $other->value;
  }

  /**
   * Method __toString
   *
   * Returns the string representation of the 
   * PermissionName object.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The string representation of the PermissionName object.
   */
  public function __toString(): string
  {
    return $this->value;
  }
  //#endregion
}
