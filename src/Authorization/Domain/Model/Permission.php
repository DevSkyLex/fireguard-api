<?php

declare(strict_types=1);

namespace Authorization\Domain\Model;

use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use DateTimeImmutable;

/**
 * Model Permission
 * @final
 *
 * Represents a permission in the RBAC system.
 * Permissions are atomic actions that can be granted to roles.
 * Format: resource.action (e.g., users.create, clients.read)
 *
 * @category Model
 * @package Authorization\Domain\Model
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
   * @param DateTimeImmutable $createdAt When the permission was created.
   */
  public function __construct(
    private PermissionId $id,
    private PermissionName $name,
    private string $description,
    private DateTimeImmutable $createdAt,
  ) {
  }
  //#endregion

  //#region Factory Methods
  /**
   * Method create
   * @static
   *
   * Factory method to create a new permission.
   *
   * @access public
   * @since 1.0.0
   *
   * @param PermissionId $id The permission ID.
   * @param PermissionName $name The permission name.
   * @param string $description The permission description.
   *
   * @return self The new permission instance.
   */
  public static function create(
    PermissionId $id,
    PermissionName $name,
    string $description = '',
  ): self {
    return new self(
      id: $id,
      name: $name,
      description: $description,
      createdAt: new DateTimeImmutable(),
    );
  }
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
   * Method createdAt
   *
   * Returns when the permission was created.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The creation timestamp.
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
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
