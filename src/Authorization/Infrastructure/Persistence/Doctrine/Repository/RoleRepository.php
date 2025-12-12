<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Port\Outbound\RoleRepositoryPort;
use Authorization\Domain\Model\Role;
use Authorization\Domain\ValueObject\RoleId;
use Authorization\Domain\ValueObject\RoleName;
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\RoleMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\RoleRecord;
use Doctrine\ORM\EntityManagerInterface;
use Shared\Domain\ValueObject\TenantId;
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;

/**
 * Repository RoleRepository
 * @final
 *
 * Doctrine implementation of RoleRepositoryPort.
 *
 * @category Repository
 * @package Authorization\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class RoleRepository implements RoleRepositoryPort
{
  //#region Constructor
  /**
   * Constructor
   * 
   * Initializes the repository with 
   * the entity manager and mapper.
   * 
   * @access private
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager The entity manager.
   * @param RoleMapper $mapper The role mapper.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly RoleMapper $mapper,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method findById
   * {@inheritDoc}
   * 
   * Finds a role by its ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleId $id The role ID.
   *
   * @return Role|null The role or null if not found.
   */
  public function findById(RoleId $id): ?Role
  {
    $record = $this->entityManager->find(
      className: RoleRecord::class,
      id: $id->value
    );

    if ($record === null) return null; 

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findByName
   * {@inheritDoc}
   * 
   * Finds a role by its name.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param RoleName $name The role name.
   *
   * @return Role|null The role or null if not found.
   */
  public function findByName(RoleName $name): ?Role
  {
    $record = $this->entityManager
      ->getRepository(className: RoleRecord::class)
      ->findOneBy(criteria: ['name' => $name->value]);

    if ($record === null) return null;

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findAll
   * {@inheritDoc}
   * 
   * Finds all roles.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param ?TenantId $tenantId The tenant ID.
   *
   * @return array<Role> The roles.
   */
  public function findAll(?TenantId $tenantId = null): array
  {
    $criteria = [];
    if ($tenantId !== null) {
      $criteria['tenantId'] = (string) $tenantId;
    }

    $records = $this->entityManager
      ->getRepository(className: RoleRecord::class)
      ->findBy(criteria: $criteria);

    return array_map(
      fn(RoleRecord $record) => $this->mapper->toDomain(record: $record),
      $records
    );
  }

  /**
   * Method save
   * {@inheritDoc}
   * 
   * Persists a role.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Role $role The role to save.
   *
   * @return void None.
   */
  public function save(Role $role): void
  {
    $existingRecord = $this->entityManager->find(
      className: RoleRecord::class,
      id: $role->id()->value
    );

    $record = $this->mapper->toRecord(
      role: $role, 
      record: $existingRecord
    );

    // Handle permissions collection
    $record->permissions->clear();
    foreach ($role->permissions() as $permission) {
      $permissionRecord = $this->entityManager->getReference(
        entityName: PermissionRecord::class,
        id: $permission->id()->value
      );
      $record->permissions->add($permissionRecord);
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
   * @access public
   * @since 1.0.0
   *
   * @param Role $role The role to delete.
   *
   * @return void None.
   */
  public function delete(Role $role): void
  {
    $record = $this->entityManager->find(
      className: RoleRecord::class,
      id: $role->id()->value
    );

    if ($record !== null) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  //#endregion
}
