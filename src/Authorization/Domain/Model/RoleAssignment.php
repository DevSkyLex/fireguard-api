<?php

declare(strict_types=1);

namespace Authorization\Domain\Model;

use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;
use DateTimeImmutable;
use Shared\Domain\ValueObject\TenantId;

/**
 * Model RoleAssignment.
 *
 * @category Model
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignment
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the RoleAssignment class.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentId       $id          the assignment ID
   * @param RoleId                 $roleId      the role ID being assigned
   * @param SubjectType            $subjectType the type of subject (user)
   * @param string                 $subjectId   the ID of the subject
   * @param TenantId|null          $tenantId    the tenant ID for multi-tenant support
   * @param DateTimeImmutable      $assignedAt  when the role was assigned
   * @param DateTimeImmutable|null $expiresAt   when the assignment expires (optional)
   */
  private function __construct(
    private RoleAssignmentId $id,
    private RoleId $roleId,
    private SubjectType $subjectType,
    private string $subjectId,
    private ?TenantId $tenantId,
    private DateTimeImmutable $assignedAt,
    private ?DateTimeImmutable $expiresAt,
  ) {
  }
  // #endregion

  // #region Factory Methods
  /**
   * Method assignToUser.
   *
   * @static
   *
   * Creates a role assignment for a user.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentId       $id        the assignment ID
   * @param RoleId                 $roleId    the role ID
   * @param string                 $userId    the user ID
   * @param TenantId|null          $tenantId  the tenant ID
   * @param DateTimeImmutable|null $expiresAt when the assignment expires
   *
   * @return self the new role assignment
   */
  public static function assignToUser(
    RoleAssignmentId $id,
    RoleId $roleId,
    string $userId,
    ?TenantId $tenantId = null,
    ?DateTimeImmutable $expiresAt = null,
  ): self {
    return new self(
      id: $id,
      roleId: $roleId,
      subjectType: SubjectType::USER,
      subjectId: $userId,
      tenantId: $tenantId,
      assignedAt: new DateTimeImmutable(),
      expiresAt: $expiresAt,
    );
  }

  /**
   * Method reconstitute.
   *
   * @static
   *
   * Reconstitutes a role assignment from persistence.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentId       $id          the assignment ID
   * @param RoleId                 $roleId      the role ID
   * @param SubjectType            $subjectType the subject type
   * @param string                 $subjectId   the subject ID
   * @param TenantId|null          $tenantId    the tenant ID
   * @param DateTimeImmutable      $assignedAt  when assigned
   * @param DateTimeImmutable|null $expiresAt   when expires
   *
   * @return self the reconstituted role assignment
   */
  public static function reconstitute(
    RoleAssignmentId $id,
    RoleId $roleId,
    SubjectType $subjectType,
    string $subjectId,
    ?TenantId $tenantId,
    DateTimeImmutable $assignedAt,
    ?DateTimeImmutable $expiresAt,
  ): self {
    return new self(
      id: $id,
      roleId: $roleId,
      subjectType: $subjectType,
      subjectId: $subjectId,
      tenantId: $tenantId,
      assignedAt: $assignedAt,
      expiresAt: $expiresAt,
    );
  }
  // #endregion

  // #region Methods
  /**
   * Method id.
   *
   * Returns the assignment ID.
   *
   * @since 1.0.0
   *
   * @return RoleAssignmentId the assignment ID
   */
  public function id(): RoleAssignmentId
  {
    return $this->id;
  }

  /**
   * Method roleId.
   *
   * Returns the role ID.
   *
   * @since 1.0.0
   *
   * @return RoleId the role ID
   */
  public function roleId(): RoleId
  {
    return $this->roleId;
  }

  /**
   * Method subjectType.
   *
   * Returns the subject type.
   *
   * @since 1.0.0
   *
   * @return SubjectType the subject type
   */
  public function subjectType(): SubjectType
  {
    return $this->subjectType;
  }

  /**
   * Method subjectId.
   *
   * Returns the subject ID.
   *
   * @since 1.0.0
   *
   * @return string the subject ID
   */
  public function subjectId(): string
  {
    return $this->subjectId;
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
   * Method assignedAt.
   *
   * Returns when the role was assigned.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable the assignment timestamp
   */
  public function assignedAt(): DateTimeImmutable
  {
    return $this->assignedAt;
  }

  /**
   * Method expiresAt.
   *
   * Returns when the assignment expires.
   *
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null the expiration timestamp
   */
  public function expiresAt(): ?DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method isExpired.
   *
   * Checks if the assignment has expired.
   *
   * @since 1.0.0
   *
   * @return bool true if expired
   */
  public function isExpired(): bool
  {
    if (null === $this->expiresAt) {
      return false;
    }

    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method isActive.
   *
   * Checks if the assignment is currently active.
   *
   * @since 1.0.0
   *
   * @return bool true if active
   */
  public function isActive(): bool
  {
    return !$this->isExpired();
  }

  /**
   * Method equals.
   *
   * Compares two RoleAssignment objects for equality.
   *
   * @since 1.0.0
   *
   * @param self $other the other RoleAssignment to compare
   *
   * @return bool true if equal
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  // #endregion
}
