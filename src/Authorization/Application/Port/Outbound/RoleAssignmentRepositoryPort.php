<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Role;
use Authorization\Domain\Model\RoleAssignment;
use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;

/**
 * Interface RoleAssignmentRepositoryPort.
 *
 * Port for role assignment persistence operations.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RoleAssignmentRepositoryPort
{
  // #region Methods
  /**
   * Method findById.
   *
   * Finds a role assignment by its ID.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id the assignment ID
   *
   * @return RoleAssignment|null the assignment or null if not found
   */
  public function findById(RoleAssignmentId $id): ?RoleAssignment;

  /**
   * Method findBySubject.
   *
   * Finds all role assignments for a subject.
   *
   * @since 1.0.0
   *
   * @param SubjectType $subjectType the subject type
   * @param string $subjectId the subject ID
   *
   * @return array<RoleAssignment> the assignments
   */
  public function findBySubject(SubjectType $subjectType, string $subjectId): array;

  /**
   * Method findRolesForSubject.
   *
   * Finds all roles assigned to a subject
   * (including permissions).
   *
   * @since 1.0.0
   *
   * @param SubjectType $subjectType the subject type
   * @param string $subjectId the subject ID
   *
   * @return array<Role> the roles with their permissions
   */
  public function findRolesForSubject(SubjectType $subjectType, string $subjectId): array;

  /**
   * Method findByRole.
   *
   * Finds all assignments for
   * a specific role.
   *
   * @since 1.0.0
   *
   * @param RoleId $roleId the role ID
   *
   * @return array<RoleAssignment> the assignments
   */
  public function findByRole(RoleId $roleId): array;

  /**
   * Method save.
   *
   * Persists a role assignment.
   *
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment the assignment to save
   *
   * @return void none
   */
  public function save(RoleAssignment $assignment): void;

  /**
   * Method delete.
   *
   * Deletes a role assignment.
   *
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment the assignment to delete
   *
   * @return void none
   */
  public function delete(RoleAssignment $assignment): void;

  /**
   * Method deleteBySubject.
   *
   * Deletes all role assignments for a subject.
   *
   * @since 1.0.0
   *
   * @param SubjectType $subjectType the subject type
   * @param string $subjectId the subject ID
   *
   * @return void none
   */
  public function deleteBySubject(SubjectType $subjectType, string $subjectId): void;
  // #endregion
}
