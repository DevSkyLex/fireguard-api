<?php

declare(strict_types=1);

namespace Authorization\Domain\Model\Role;

use Authorization\Domain\Model\Permission\Permission;
use Authorization\Domain\ValueObject\{RoleId, RoleName};
use DateTimeImmutable;
use Shared\Domain\ValueObject\TenantId;

use function array_filter;
use function array_values;

/**
 * Model Role.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class Role
{
  // #region Properties
  /**
   * Property permissions.
   *
   * Collection of permissions assigned to this role.
   *
   * @since 1.0.0
   *
   * @var array<Permission>
   */
  private array $permissions = [];
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the Role class.
   *
   * @since 1.0.0
   *
   * @param RoleId $id the role ID
   * @param RoleName $name the role name
   * @param string $description the role description
   * @param bool $isSystem whether this is a system role (cannot be deleted)
   * @param TenantId|null $tenantId the tenant ID for multi-tenant support
   * @param DateTimeImmutable $createdAt when the role was created
   * @param DateTimeImmutable|null $updatedAt when the role was last updated
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
  // #endregion

  // #region Factory Methods
  /**
   * Method create.
   *
   * @static
   *
   * Factory method to create a new role.
   *
   * @since 1.0.0
   *
   * @param RoleId $id the role ID
   * @param RoleName $name the role name
   * @param string $description the role description
   * @param bool $isSystem whether this is a system role
   * @param TenantId|null $tenantId the tenant ID
   *
   * @return self the new role instance
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
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a role from persistence.
   *
   * @since 1.0.0
   *
   * @param RoleId $id the role ID
   * @param RoleName $name the role name
   * @param string $description the role description
   * @param bool $isSystem whether this is a system role
   * @param TenantId|null $tenantId the tenant ID
   * @param DateTimeImmutable $createdAt when the role was created
   * @param DateTimeImmutable|null $updatedAt when the role was last updated
   * @param array<Permission> $permissions the role's permissions
   *
   * @return self the reconstituted role
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
  // #endregion

  // #region Methods
  /**
   * Method id.
   *
   * Returns the role ID.
   *
   * @since 1.0.0
   *
   * @return RoleId the role ID
   */
  public function id(): RoleId
  {
    return $this->id;
  }

  /**
   * Method name.
   *
   * Returns the role name.
   *
   * @since 1.0.0
   *
   * @return RoleName the role name
   */
  public function name(): RoleName
  {
    return $this->name;
  }

  /**
   * Method description.
   *
   * Returns the role description.
   *
   * @since 1.0.0
   *
   * @return string the role description
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method isSystem.
   *
   * Returns whether this is a system role.
   *
   * @since 1.0.0
   *
   * @return bool true if this is a system role
   */
  public function isSystem(): bool
  {
    return $this->isSystem;
  }

  /**
   * Method tenantId.
   *
   * Returns the tenant ID.
   *
   * @since 1.0.0
   *
   * @return TenantId|null the tenant ID
   */
  public function tenantId(): ?TenantId
  {
    return $this->tenantId;
  }

  /**
   * Method createdAt.
   *
   * Returns when the role was created.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the creation timestamp
   */
  public function createdAt(): DateTimeImmutable
  {
    return $this->createdAt;
  }

  /**
   * Method updatedAt.
   *
   * Returns when the role was last updated.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the update timestamp
   */
  public function updatedAt(): ?DateTimeImmutable
  {
    return $this->updatedAt;
  }

  /**
   * Method permissions.
   *
   * Returns the permissions assigned to this role.
   *
   * @since 1.0.0
   *
   * @return array<Permission> the permissions
   */
  public function permissions(): array
  {
    return $this->permissions;
  }

  /**
   * Method update.
   *
   * Updates the role's name and description.
   *
   * @since 1.0.0
   *
   * @param RoleName $name the new name
   * @param string $description the new description
   */
  public function update(RoleName $name, string $description): void
  {
    $this->name = $name;
    $this->description = $description;
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method addPermission.
   *
   * Adds a permission to this role.
   *
   * @since 1.0.0
   *
   * @param Permission $permission the permission to add
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
   * Method removePermission.
   *
   * Removes a permission from this role.
   *
   * @since 1.0.0
   *
   * @param Permission $permission the permission to remove
   */
  public function removePermission(Permission $permission): void
  {
    $this->permissions = array_values(array_filter(
      $this->permissions,
      fn (Permission $existing) => !$existing->equals($permission),
    ));
    $this->updatedAt = new DateTimeImmutable();
  }

  /**
   * Method hasPermission.
   *
   * Checks if this role has a specific permission.
   * Uses wildcard matching (e.g., users.* matches users.create).
   *
   * @since 1.0.0
   *
   * @param Permission $permission the permission to check
   *
   * @return bool true if the role has the permission
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
   * Method equals.
   *
   * Compares two Role objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other Role object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  // #endregion
}
