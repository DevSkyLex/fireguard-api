<?php

declare(strict_types=1);

namespace Organization\Domain\Model\OrganizationRole;

use DateTimeImmutable;
use InvalidArgumentException;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};

/**
 * Model OrganizationRole.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class OrganizationRole
{
  // #region Properties
  /**
   * @var list<string>
   */
  private array $permissions;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationRole class.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleId $id the role identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationRoleName $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is a system role
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param string $description the role description
   */
  private function __construct(
    private OrganizationRoleId $id,
    private OrganizationId $organizationId,
    private OrganizationRoleName $name,
    array $permissions,
    private bool $isSystem,
    private DateTimeImmutable $createdAt,
    private string $description = '',
  ) {
    $this->permissions = $permissions;
  }
  // #endregion

  // #region Methods
  /**
   * Method create.
   *
   * Creates a new organization role aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleId $id the role identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationRoleName $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is a system role
   * @param string $description the role description
   *
   * @return self the created role aggregate
   */
  public static function create(
    OrganizationRoleId $id,
    OrganizationId $organizationId,
    OrganizationRoleName $name,
    array $permissions,
    bool $isSystem = false,
    string $description = '',
  ): self {
    return new self(
      id: $id,
      organizationId: $organizationId,
      name: $name,
      permissions: $permissions,
      isSystem: $isSystem,
      createdAt: new DateTimeImmutable(),
      description: $description,
    );
  }

  /**
   * Method reconstitute.
   *
   * Reconstitutes an organization role aggregate from persisted state.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleId $id the role identifier
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationRoleName $name the role name
   * @param list<string> $permissions the role permissions
   * @param bool $isSystem whether the role is a system role
   * @param DateTimeImmutable $createdAt the creation timestamp
   * @param string $description the role description
   *
   * @return self the reconstituted role aggregate
   */
  public static function reconstitute(
    OrganizationRoleId $id,
    OrganizationId $organizationId,
    OrganizationRoleName $name,
    array $permissions,
    bool $isSystem,
    DateTimeImmutable $createdAt,
    string $description = '',
  ): self {
    return new self($id, $organizationId, $name, $permissions, $isSystem, $createdAt, $description);
  }

  /**
   * Method id.
   *
   * Returns the role identifier.
   *
   * @since 1.0.0
   *
   * @return OrganizationRoleId the role identifier
   */
  public function id(): OrganizationRoleId
  {
    return $this->id;
  }

  /**
   * Method organizationId.
   *
   * Returns the organization identifier.
   *
   * @since 1.0.0
   *
   * @return OrganizationId the organization identifier
   */
  public function organizationId(): OrganizationId
  {
    return $this->organizationId;
  }

  /**
   * Method name.
   *
   * Returns the role name.
   *
   * @since 1.0.0
   *
   * @return OrganizationRoleName the role name
   */
  public function name(): OrganizationRoleName
  {
    return $this->name;
  }

  /**
   * Method permissions.
   *
   * Returns role permissions.
   *
   * @since 1.0.0
   *
   * @return list<string> the role permissions
   */
  public function permissions(): array
  {
    return $this->permissions;
  }

  /**
   * Method isSystem.
   *
   * Checks whether the role is system-managed.
   *
   * @since 1.0.0
   *
   * @return bool true when role is system-managed, false otherwise
   */
  public function isSystem(): bool
  {
    return $this->isSystem;
  }

  /**
   * Method createdAt.
   *
   * Returns the creation timestamp.
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
   * Method rename.
   *
   * Renames the role. System roles cannot be renamed — their names are
   * fixed identifiers referenced by the system role catalog and by callers
   * matching on well-known names (e.g. the "admin" role granted to a new
   * owner on ownership transfer).
   *
   * @since 1.1.0
   *
   * @param OrganizationRoleName $name the new role name
   *
   * @throws InvalidArgumentException when the role is system-managed
   */
  public function rename(OrganizationRoleName $name): void
  {
    if ($this->isSystem) {
      throw new InvalidArgumentException('System roles cannot be renamed.');
    }

    $this->name = $name;
  }

  /**
   * Method updatePermissions.
   *
   * Replaces role permissions.
   *
   * @since 1.0.0
   *
   * @param list<string> $permissions the role permissions
   */
  public function updatePermissions(array $permissions): void
  {
    $this->permissions = $permissions;
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
   * Method updateDescription.
   *
   * Updates the role description.
   *
   * @since 1.0.0
   *
   * @param string $description the new description
   */
  public function updateDescription(string $description): void
  {
    $this->description = $description;
  }
  // #endregion
}
