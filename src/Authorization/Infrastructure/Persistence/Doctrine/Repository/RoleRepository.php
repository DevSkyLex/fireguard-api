<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Role\Role;
use Authorization\Domain\ValueObject\{RoleId, RoleName};
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\RoleMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\{PermissionRecord, RoleRecord};
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\TenantId;

use function array_map;

/**
 * Repository RoleRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleRepository implements RoleRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes the repository with
   * the entity manager and mapper.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   * @param RoleMapper $mapper the role mapper
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly RoleMapper $mapper,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method findById
   * {@inheritDoc}
   *
   * Finds a role by its ID.
   *
   * @since 1.0.0
   *
   * @param RoleId $id the role ID
   *
   * @return Role|null the role or null if not found
   */
  public function findById(RoleId $id): ?Role
  {
    $record = $this->entityManager->find(
      className: RoleRecord::class,
      id: $id->value,
    );

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findByName
   * {@inheritDoc}
   *
   * Finds a role by its name.
   *
   * @since 1.0.0
   *
   * @param RoleName $name the role name
   *
   * @return Role|null the role or null if not found
   */
  public function findByName(RoleName $name): ?Role
  {
    $record = $this->entityManager
      ->getRepository(className: RoleRecord::class)
      ->findOneBy(criteria: ['name' => $name->value]);

    if (null === $record) {
      return null;
    }

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findAll
   * {@inheritDoc}
   *
   * Finds all roles.
   *
   * @since 1.0.0
   *
   * @param ?TenantId $tenantId the tenant ID
   * @param ?bool $isSystem optional filter for system roles
   *
   * @return array<Role> the roles
   */
  public function findAll(?TenantId $tenantId = null, ?bool $isSystem = null): array
  {
    $criteria = [];
    if (null !== $tenantId) {
      $criteria['tenantId'] = (string) $tenantId;
    }
    if (null !== $isSystem) {
      $criteria['isSystem'] = $isSystem;
    }

    $records = $this->entityManager
      ->getRepository(className: RoleRecord::class)
      ->findBy(criteria: $criteria);

    return array_map(
      fn (RoleRecord $record) => $this->mapper->toDomain(record: $record),
      $records,
    );
  }

  /**
   * Method save
   * {@inheritDoc}
   *
   * Persists a role.
   *
   * @since 1.0.0
   *
   * @param Role $role the role to save
   *
   * @return void none
   */
  public function save(Role $role): void
  {
    $existingRecord = $this->entityManager->find(
      className: RoleRecord::class,
      id: $role->id()->value,
    );

    $record = $this->mapper->toRecord(
      role: $role,
      record: $existingRecord,
    );

    // Handle permissions collection
    $record->permissions->clear();
    foreach ($role->permissions() as $permission) {
      $permissionRecord = $this->entityManager->getReference(
        entityName: PermissionRecord::class,
        id: $permission->id()->value,
      );
      if (null !== $permissionRecord) {
        $record->permissions->add($permissionRecord);
      }
    }

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method delete
   * {@inheritDoc}
   *
   * Deletes a role.
   *
   * @since 1.0.0
   *
   * @param Role $role the role to delete
   *
   * @return void none
   */
  public function delete(Role $role): void
  {
    $record = $this->entityManager->find(
      className: RoleRecord::class,
      id: $role->id()->value,
    );

    if (null !== $record) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  // #endregion
}
