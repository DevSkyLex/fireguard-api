<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Port\Outbound\RoleAssignmentRepositoryPort;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\Model\RoleAssignment\RoleAssignment;
use Authorization\Domain\ValueObject\{RoleAssignmentId, RoleId, SubjectType};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\{RoleAssignmentMapper, RoleMapper};
use Authorization\Infrastructure\Persistence\Doctrine\Record\{RoleAssignmentRecord, RoleRecord};
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

use function array_filter;
use function array_map;
use function array_values;
use function is_array;

/**
 * Repository RoleAssignmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleAssignmentRepository implements RoleAssignmentRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the repository with the entity manager,
   * role assignment mapper, and role mapper.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param RoleAssignmentMapper $mapper the role assignment mapper
   * @param RoleMapper $roleMapper the role mapper
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private RoleAssignmentMapper $mapper,
    private RoleMapper $roleMapper,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method findById
   * {@inheritDoc}
   *
   * Finds a role assignment by its ID.
   *
   * @since 1.0.0
   *
   * @param RoleAssignmentId $id the assignment ID
   *
   * @return RoleAssignment|null the assignment or null if not found
   */
  public function findById(RoleAssignmentId $id): ?RoleAssignment
  {
    $record = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $id->value,
    );

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findBySubject
   * {@inheritDoc}
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

    if (!is_array($records)) {
      return [];
    }

    /** @var list<RoleAssignmentRecord> $filteredRecords */
    $filteredRecords = array_values(array_filter(
      $records,
      static fn ($r): bool => $r instanceof RoleAssignmentRecord,
    ));

    return array_map(
      fn (RoleAssignmentRecord $record): RoleAssignment => $this->mapper->toDomain(record: $record),
      $filteredRecords,
    );
  }

  /**
   * Method findRolesForSubject
   * {@inheritDoc}
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
  public function findRolesForSubject(SubjectType $subjectType, string $subjectId): array
  {
    $assignments = $this->findBySubject(subjectType: $subjectType, subjectId: $subjectId);

    $roles = [];
    foreach ($assignments as $assignment) {
      $roleRecord = $this->entityManager->find(RoleRecord::class, $assignment->roleId()->value);
      if (null !== $roleRecord) {
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
   * @since 1.0.0
   *
   * @param RoleId $roleId the role ID
   *
   * @return array<RoleAssignment> the assignments
   */
  public function findByRole(RoleId $roleId): array
  {
    $records = $this->entityManager
      ->getRepository(RoleAssignmentRecord::class)
      ->findBy(['roleId' => $roleId->value]);

    return array_map(
      fn (RoleAssignmentRecord $record) => $this->mapper->toDomain(record: $record),
      $records,
    );
  }

  /**
   * Method save
   * {@inheritDoc}
   *
   * Persists a role assignment.
   *
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment the assignment to save
   *
   * @return void none
   */
  public function save(RoleAssignment $assignment): void
  {
    $existingRecord = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $assignment->id()->value,
    );

    $record = $this->mapper->toRecord(
      assignment: $assignment,
      record: $existingRecord,
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
   * @since 1.0.0
   *
   * @param RoleAssignment $assignment the assignment to delete
   *
   * @return void none
   */
  public function delete(RoleAssignment $assignment): void
  {
    $record = $this->entityManager->find(
      className: RoleAssignmentRecord::class,
      id: $assignment->id()->value,
    );

    if (null !== $record) {
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
   * @since 1.0.0
   *
   * @param SubjectType $subjectType the subject type
   * @param string $subjectId the subject ID
   *
   * @return void none
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
  // #endregion
}
