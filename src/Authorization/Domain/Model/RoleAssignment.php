<?php

declare(strict_types=1);

namespace Authorization\Domain\Model;

use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;
use DateTimeImmutable;
use Shared\Domain\ValueObject\TenantId;

/**
 * Model RoleAssignment
 * @final
 *
 * Represents an assignment of a role to a user.
 * This enables flexible RBAC for users in the SSO.
 *
 * @category Model
 * @package Authorization\Domain\Model
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignment
{
  //#region Constructor
  /**
   * Constructor
   *
   * Initializes a new instance of the RoleAssignment class.
   *
   * @access private
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id The assignment ID.
   * @param RoleId $roleId The role ID being assigned.
   * @param SubjectType $subjectType The type of subject (user).
   * @param string $subjectId The ID of the subject.
   * @param TenantId|null $tenantId The tenant ID for multi-tenant support.
   * @param DateTimeImmutable $assignedAt When the role was assigned.
   * @param DateTimeImmutable|null $expiresAt When the assignment expires (optional).
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
  //#endregion

  //#region Factory Methods
  /**
   * Method assignToUser
   * @static
   *
   * Creates a role assignment for a user.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id The assignment ID.
   * @param RoleId $roleId The role ID.
   * @param string $userId The user ID.
   * @param TenantId|null $tenantId The tenant ID.
   * @param DateTimeImmutable|null $expiresAt When the assignment expires.
   *
   * @return self The new role assignment.
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
   * Method reconstitute
   * @static
   *
   * Reconstitutes a role assignment from persistence.
   *
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id The assignment ID.
   * @param RoleId $roleId The role ID.
   * @param SubjectType $subjectType The subject type.
   * @param string $subjectId The subject ID.
   * @param TenantId|null $tenantId The tenant ID.
   * @param DateTimeImmutable $assignedAt When assigned.
   * @param DateTimeImmutable|null $expiresAt When expires.
   *
   * @return self The reconstituted role assignment.
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
  //#endregion

  //#region Methods
  /**
   * Method id
   *
   * Returns the assignment ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return RoleAssignmentId The assignment ID.
   */
  public function id(): RoleAssignmentId
  {
    return $this->id;
  }

  /**
   * Method roleId
   *
   * Returns the role ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return RoleId The role ID.
   */
  public function roleId(): RoleId
  {
    return $this->roleId;
  }

  /**
   * Method subjectType
   *
   * Returns the subject type.
   *
   * @access public
   * @since 1.0.0
   *
   * @return SubjectType The subject type.
   */
  public function subjectType(): SubjectType
  {
    return $this->subjectType;
  }

  /**
   * Method subjectId
   *
   * Returns the subject ID.
   *
   * @access public
   * @since 1.0.0
   *
   * @return string The subject ID.
   */
  public function subjectId(): string
  {
    return $this->subjectId;
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
   * Method assignedAt
   *
   * Returns when the role was assigned.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable The assignment timestamp.
   */
  public function assignedAt(): DateTimeImmutable
  {
    return $this->assignedAt;
  }

  /**
   * Method expiresAt
   *
   * Returns when the assignment expires.
   *
   * @access public
   * @since 1.0.0
   *
   * @return DateTimeImmutable|null The expiration timestamp.
   */
  public function expiresAt(): ?DateTimeImmutable
  {
    return $this->expiresAt;
  }

  /**
   * Method isExpired
   *
   * Checks if the assignment has expired.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if expired.
   */
  public function isExpired(): bool
  {
    if ($this->expiresAt === null) {
      return false;
    }
    return $this->expiresAt < new DateTimeImmutable();
  }

  /**
   * Method isActive
   *
   * Checks if the assignment is currently active.
   *
   * @access public
   * @since 1.0.0
   *
   * @return bool True if active.
   */
  public function isActive(): bool
  {
    return !$this->isExpired();
  }

  /**
   * Method equals
   *
   * Compares two RoleAssignment objects for equality.
   *
   * @access public
   * @since 1.0.0
   *
   * @param self $other The other RoleAssignment to compare.
   *
   * @return bool True if equal.
   */
  public function equals(self $other): bool
  {
    return $this->id->equals(other: $other->id);
  }
  //#endregion
}
