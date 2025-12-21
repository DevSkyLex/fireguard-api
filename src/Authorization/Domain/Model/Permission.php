<?php

declare(strict_types=1);

namespace Authorization\Domain\Model;

use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use DateTimeImmutable;

/**
 * Model Permission.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class Permission
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of
   * the Permission class.
   *
   * @since 1.0.0
   *
   * @param PermissionId $id the permission ID
   * @param PermissionName $name the permission name
   * @param string $description the permission description
   * @param DateTimeImmutable $createdAt when the permission was created
   */
  public function __construct(
    private PermissionId $id,
    private PermissionName $name,
    private string $description,
    private DateTimeImmutable $createdAt,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method create.
   *
   * @static
   *
   * Factory method to create a new permission.
   *
   * @since 1.0.0
   *
   * @param PermissionId $id the permission ID
   * @param PermissionName $name the permission name
   * @param string $description the permission description
   *
   * @return self the new permission instance
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
  // #endregion

  // #region Methods
  /**
   * Method id.
   *
   * Returns the permission ID.
   *
   * @since 1.0.0
   *
   * @return PermissionId the permission ID
   */
  public function id(): PermissionId
  {
    return $this->id;
  }

  /**
   * Method name.
   *
   * Returns the permission name.
   *
   * @since 1.0.0
   *
   * @return PermissionName the permission name
   */
  public function name(): PermissionName
  {
    return $this->name;
  }

  /**
   * Method description.
   *
   * Returns the permission description.
   *
   * @since 1.0.0
   *
   * @return string the permission description
   */
  public function description(): string
  {
    return $this->description;
  }

  /**
   * Method createdAt.
   *
   * Returns when the permission was created.
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
   * Method matches.
   *
   * Checks if this permission matches the required permission.
   * Delegates to PermissionName for wildcard matching.
   *
   * @since 1.0.0
   *
   * @param PermissionName $required the required permission
   *
   * @return bool true if this permission matches the required permission
   */
  public function matches(PermissionName $required): bool
  {
    return $this->name->matches(required: $required);
  }

  /**
   * Method equals.
   *
   * Compares two Permission objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other Permission object to compare
   *
   * @return bool true if the objects are equal, false otherwise
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  // #endregion
}
