<?php

declare(strict_types=1);

namespace Authorization\Application\Port\Outbound;

use Authorization\Domain\Model\Role;
use Authorization\Domain\Model\RoleAssignment;
use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;

/**
 * Interface RoleAssignmentRepositoryPort
 *
 * Port for role assignment persistence operations.
 *
 * @category Port
 * @package Authorization\Application\Port\Outbound
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface RoleAssignmentRepositoryPort
{
  //#region Methods
  /**
   * Method findById
   *
   * Finds a role assignment by its ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id The assignment ID.
   *
   * @return RoleAssignment|null The assignment or null if not found.
   */
  public function findById(RoleAssignmentId $id): ?RoleAssignment;

  /**
   * Method findBySubject
   *
   * Finds all role assignments for a subject.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param SubjectType $subjectType The subject type.
   * @param string $subjectId The subject ID.
   *
   * @return array<RoleAssignment> The assignments.
   */
  public function findBySubject(SubjectType $subjectType, string $subjectId): array;

  /**
   * Method findRolesForSubject
   *
   * Finds all roles assigned to a subject 
   * (including permissions).
   * 
   * @access public
   * @since 1.0.0
   *
   * @param SubjectType $subjectType The subject type.
   * @param string $subjectId The subject ID.
   *
   * @return array<Role> The roles with their permissions.
   */
  public function findRolesForSubject(SubjectType $subjectType, string $subjectId): array;

  /**
   * Method findByRole
   *
   * Finds all assignments for 
   * a specific role.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $roleId The role ID.
   *
   * @return array<RoleAssignment> The assignments.
   */
  public function findByRole(RoleId $roleId): array;

  /**
   * Method save
   *
   * Persists a role assignment.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment The assignment to save.
   *
   * @return void None.
   */
  public function save(RoleAssignment $assignment): void;

  /**
   * Method delete
   *
   * Deletes a role assignment.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment The assignment to delete.
   *
   * @return void None.
   */
  public function delete(RoleAssignment $assignment): void;

  /**
   * Method deleteBySubject
   *
   * Deletes all role assignments for a subject.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param SubjectType $subjectType The subject type.
   * @param string $subjectId The subject ID.
   *
   * @return void None.
   */
  public function deleteBySubject(SubjectType $subjectType, string $subjectId): void;
  //#endregion
}
