<?php

declare(strict_types=1);

namespace Authorization\Domain\Model;

use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use DateTimeImmutable;
use Shared\Domain\ValueObject\TenantId;

/**
 * Model Role
 * @final
 *
 * Represents a role in the RBAC system.
 * Roles are collections of permissions that can be assigned to subjects.
 * This is the aggregate root for the Authorization bounded context.
 *
 * @category Model
 * @package Authorization\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Role
{
  //#region Properties
  /**
   * Property permissions
   *
   * Collection of permissions assigned to this role.
   *
   * @access private
   * @since 1.0.0
   *
   * @var array<Permission>
   */
  private array $permissions = [];
  //#endregion

  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the Role class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param RoleId $id The role ID.
   * @param RoleName $name The role name.
   * @param string $description The role description.
   * @param bool $isSystem Whether this is a system role (cannot be deleted).
   * @param TenantId|null $tenantId The tenant ID for multi-tenant support.
   * @param DateTimeImmutable $createdAt When the role was created.
   * @param DateTimeImmutable|null $updatedAt When the role was last updated.
   */
  private function __construct(
    private readonly RoleId $id,
    private RoleName $name,
    private string $description,
    private readonly bool $isSystem,
    private readonly ?TenantId $tenantId,
    private readonly DateTimeImmutable $createdAt,
    private ?DateTimeImmutable $updatedAt = null,
  ) {
  }
  //#endregion

  //#region Factory Methods
  /**
   * Method create
   * @static
   *
   * Factory method to create a new role.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $id The role ID.
   * @param RoleName $name The role name.
   * @param string $description The role description.
   * @param bool $isSystem Whether this is a system role.
   * @param TenantId|null $tenantId The tenant ID.
   *
   * @return self The new role instance.
   */
  public static function create(
    RoleId $id,
    RoleName $name,
    string $description = '',
    bool $isSystem = false,
    ?TenantId $tenantId = null,
  ): self {
    return new self(
      id: $id,
      name: $name,
      description: $description,
      isSystem: $isSystem,
      tenantId: $tenantId,
      createdAt: new DateTimeImmutable(),
    );
  }

  /**
   * Method reconstitute
   * @static
   *
   * Reconstitutes a role from persistence.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $id The role ID.
   * @param RoleName $name The role name.
   * @param string $description The role description.
   * @param bool $isSystem Whether this is a system role.
   * @param TenantId|null $tenantId The tenant ID.
   * @param DateTimeImmutable $createdAt When the role was created.
   * @param DateTimeImmutable|null $updatedAt When the role was last updated.
   * @param array<Permission> $permissions The role's permissions.
   *
   * @return self The reconstituted role.
   */
  public static function reconstitute(
    RoleId $id,
    RoleName $name,
    string $description,
    bool $isSystem,
    ?TenantId $tenantId,
    DateTimeImmutable $createdAt,
    ?DateTimeImmutable $updatedAt,
    array $permissions = [],
  ): self {
    $role = new self(
      id: $id,
      name: $name,
      description: $description,
      isSystem: $isSystem,
      tenantId: $tenantId,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );
    $role->permissions = $permissions;
    return $role;
  }
  //#endregion

  //#region Methods
  /**
   * Method id
   *
   * Returns the role ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return RoleId The role ID.
   */
  public function id(): RoleId
  {
    return $this->id;
  }

  /**
   * Method name
   *
   * Returns the role name.
   *
   * @access public
   * @since 1.0.0
   *
   * @return RoleName The role name.
   */
  public function name(): RoleName
  {
    return $this->name;
  }

  /**
   * Method description
   *
   * Returns the role description.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The role description.
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method isSystem
   *
   * Returns whether this is a system role.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if this is a system role.
   */
  public function isSystem(): bool
  {
    return $this->isSystem;
  }

  /**
   * Method tenantId
   *
   * Returns the tenant ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return TenantId|null The tenant ID.
   */
  public function tenantId(): ?TenantId
  {
    return $this->tenantId;
  }

  /**
   * Method createdAt
   *
   * Returns when the role was created.
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
   * Method updatedAt
   *
   * Returns when the role was last updated.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null The update timestamp.
   */
  public function updatedAt(): ?DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method permissions
   *
   * Returns the permissions assigned to this role.
   *
   * @access public
   * @since 1.0.0
   *
   * @return array<Permission> The permissions.
   */
  public function permissions(): array
  {
    return $this->permissions;
  }

  /**
   * Method update
   *
   * Updates the role's name and description.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleName $name The new name.
   * @param string $description The new description.
   *
   * @return void
   */
  public function update(RoleName $name, string $description): void
  {
    $this->name = $name;
    $this->description = $description;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method addPermission
   *
   * Adds a permission to this role.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to add.
   *
   * @return void
   */
  public function addPermission(Permission $permission): void
  {
    foreach ($this->permissions as $existing) {
      if ($existing->equals($permission)) {
        return; // Already has this permission
      }
    }
    $this->permissions[] = $permission;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method removePermission
   *
   * Removes a permission from this role.
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to remove.
   *
   * @return void
   */
  public function removePermission(Permission $permission): void
  {
    $this->permissions = array_values(array_filter(
      $this->permissions,
      fn(Permission $existing) => !$existing->equals($permission)
    ));
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method hasPermission
   *
   * Checks if this role has a specific permission.
   * Uses wildcard matching (e.g., users.* matches users.create).
   *
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to check.
   *
   * @return bool True if the role has the permission.
   */
  public function hasPermission(Permission $permission): bool
  {
    foreach ($this->permissions as $existing) {
      if ($existing->matches($permission->name())) {
        return true;
      }
    }
    return false;
  }

  /**
   * Method equals
   *
   * Compares two Role objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other Role object to compare.
   *
   * @return bool True if the objects are equal, false otherwise.
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  //#endregion
}
