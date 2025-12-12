<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Domain\Model\Role;
use Authorization\Domain\Model\RoleAssignment;
use Authorization\Domain\ValueObject\RoleAssignmentId;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\SubjectType;
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\RoleAssignmentMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\RoleMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleAssignmentRecord;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleRecord;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;

/**
 * Repository RoleAssignmentRepository
 * @final
 *
 * Doctrine implementation of RoleAssignmentRepositoryPort.
 *
 * @category Repository
 * @package Authorization\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignmentRepository implements RoleAssignmentRepositoryPort
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes the repository with the entity manager, 
   * role assignment mapper, and role mapper.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   * @param RoleAssignmentMapper $mapper The role assignment mapper.
   * @param RoleMapper $roleMapper The role mapper.
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private RoleAssignmentMapper $mapper,
    private RoleMapper $roleMapper,
  ) {
  }
  //#endregion

  //#region Methods
  /**
   * Method findById
   * {@inheritDoc}
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
  public function findById(RoleAssignmentId $id): ?RoleAssignment
  {
    $record = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $id->value
    );

    if ($record === null) return null;

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findBySubject
   * {@inheritDoc}
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
  public function findBySubject(SubjectType $subjectType, string $subjectId): array
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->select('ra')
      ->from(RoleAssignmentRecord::class, 'ra')
      ->where('ra.subjectType = :subjectType')
      ->andWhere('ra.subjectId = :subjectId')
      ->setParameter('subjectType', $subjectType->value)
      ->setParameter('subjectId', $subjectId);

    // Filter out expired assignments
    $qb->andWhere('ra.expiresAt IS NULL OR ra.expiresAt > :now')
      ->setParameter('now', new DateTimeImmutable());

    $records = $qb->getQuery()->getResult();

    return array_map(
      fn(RoleAssignmentRecord $record) => $this->mapper->toDomain(record: $record),
      $records
    );
  }

  /**
   * Method findRolesForSubject
   * {@inheritDoc}
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
  public function findRolesForSubject(SubjectType $subjectType, string $subjectId): array
  {
    $assignments = $this->findBySubject(subjectType: $subjectType, subjectId: $subjectId);

    $roles = [];
    foreach ($assignments as $assignment) {
      $roleRecord = $this->entityManager->find(RoleRecord::class, $assignment->roleId()->value);
      if ($roleRecord !== null) {
        $roles[] = $this->roleMapper->toDomain(record: $roleRecord);
      }
    }

    return $roles;
  }

  /**
   * Method findByRole
   * {@inheritDoc}
   * 
   * Finds all role assignments for a role.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $roleId The role ID.
   *
   * @return array<RoleAssignment> The assignments.
   */
  public function findByRole(RoleId $roleId): array
  {
    $records = $this->entityManager
      ->getRepository(RoleAssignmentRecord::class)
      ->findBy(['roleId' => $roleId->value]);

    return array_map(
      fn(RoleAssignmentRecord $record) => $this->mapper->toDomain(record: $record),
      $records
    );
  }

  /**
   * Method save
   * {@inheritDoc}
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
  public function save(RoleAssignment $assignment): void
  {
    $existingRecord = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $assignment->id()->value
    );

    $record = $this->mapper->toRecord(
      assignment: $assignment, 
      record: $existingRecord
    );

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method delete
   * {@inheritDoc}
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
  public function delete(RoleAssignment $assignment): void
  {
    $record = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $assignment->id()->value
    );

    if ($record !== null) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method deleteBySubject
   * {@inheritDoc}
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
  public function deleteBySubject(SubjectType $subjectType, string $subjectId): void
  {
    $qb = $this->entityManager->createQueryBuilder();
    $qb->delete(RoleAssignmentRecord::class, 'ra')
      ->where('ra.subjectType = :subjectType')
      ->andWhere('ra.subjectId = :subjectId')
      ->setParameter('subjectType', $subjectType->value)
      ->setParameter('subjectId', $subjectId);

    $qb->getQuery()->execute();
  }
  //#endregion
}
