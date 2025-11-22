<?php

declare(strict_types=1);

namespace User\Domain\Model;

use User\Domain\ValueObject\PermissionId;
use User\Domain\ValueObject\PermissionName;

/**
 * Entity Permission
 * @final
 *
 * Represents a permission in the RBAC system.
 * Permissions are atomic actions that can be granted to roles.
 *
 * @category Model
 * @package User\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Permission
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of 
   * the Permission class.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionId $id The permission ID.
   * @param PermissionName $name The permission name.
   * @param string $description The permission description.
   */
  public function __construct(
    private readonly PermissionId $id,
    private readonly PermissionName $name,
    private readonly string $description,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method id
   *
   * Returns the permission ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return PermissionId The permission ID.
   */
  public function id(): PermissionId
  {
    return $this->id;
  }

  /**
   * Method name
   *
   * Returns the permission name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return PermissionName The permission name.
   */
  public function name(): PermissionName
  {
    return $this->name;
  }

  /**
   * Method description
   *
   * Returns the permission description.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The permission description.
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method matches
   *
   * Checks if this permission matches the required permission.
   * Delegates to PermissionName for wildcard matching.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionName $required The required permission.
   *
   * @return bool True if this permission matches the required permission.
   */
  public function matches(PermissionName $required): bool
  {
    return $this->name->matches(required: $required);
  }

  /**
   * Method equals
   *
   * Compares two Permission objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Permission object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  //#endregion
}
