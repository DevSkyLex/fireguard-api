<?php

declare(strict_types=1);

namespace Authorization\Infrastructure\Persistence\Doctrine\Repository;

use Authorization\Application\Port\Outbound\PermissionRepositoryPort;
use Authorization\Domain\Model\Permission;
use Authorization\Domain\ValueObject\PermissionId;
use Authorization\Domain\ValueObject\PermissionName;
use Authorization\Infrastructure\Persistence\Doctrine\Mapper\PermissionMapper;
use Authorization\Infrastructure\Persistence\Doctrine\Record\PermissionRecord;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Repository PermissionRepository
 * @final
 *
 * Doctrine implementation of PermissionRepositoryPort.
 *
 * @category Repository
 * @package Authorization\Infrastructure\Persistence\Doctrine\Repository
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PermissionRepository implements PermissionRepositoryPort
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
   * @param PermissionMapper $mapper The permission mapper.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly PermissionMapper $mapper,
  ) {}
  //#endregion

  //#region Methods
  /**
   * Method findById
   * {@inheritDoc}
   * 
   * Finds a permission by its ID.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param PermissionId $id The permission ID.
   *
   * @return Permission|null The permission or null if not found.
   */
  public function findById(PermissionId $id): ?Permission
  {
    $record = $this->entityManager->find(PermissionRecord::class, $id->value);

    if ($record === null) {
      return null;
    }

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findByName
   * {@inheritDoc}
   * 
   * Finds a permission by its name.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param PermissionName $name The permission name.
   *
   * @return Permission|null The permission or null if not found.
   */
  public function findByName(PermissionName $name): ?Permission
  {
    $record = $this->entityManager
      ->getRepository(className: PermissionRecord::class)
      ->findOneBy(criteria: ['name' => $name->value]);

    if ($record === null) {
      return null;
    }

    return $this->mapper->toDomain(record: $record);
  }

  /**
   * Method findAll
   * {@inheritDoc}
   * 
   * Finds all permissions.
   * 
   * @access public
   * @since 1.0.0
   *
   * @return array<Permission> The permissions.
   */
  public function findAll(): array
  {
    $records = $this->entityManager
      ->getRepository(className: PermissionRecord::class)
      ->findAll();

    return array_map(
      fn(PermissionRecord $record) => $this->mapper->toDomain(record: $record),
      $records
    );
  }

  /**
   * Method save
   * {@inheritDoc}
   * 
   * Persists a permission.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to save.
   *
   * @return void None.
   */
  public function save(Permission $permission): void
  {
    $existingRecord = $this->entityManager->find(
      className: PermissionRecord::class,
      id: $permission->id()->value
    );
    
    $record = $this->mapper->toRecord(
      permission: $permission,
      record: $existingRecord
    );

    $this->entityManager->persist($record);
    $this->entityManager->flush();
  }

  /**
   * Method delete
   * {@inheritDoc}
   * 
   * Deletes a permission.
   * 
   * @access public
   * @since 1.0.0
   *
   * @param Permission $permission The permission to delete.
   *
   * @return void None.
   */
  public function delete(Permission $permission): void
  {
    $record = $this->entityManager->find(
      className: PermissionRecord::class,
      id: $permission->id()->value
    );

    if ($record !== null) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  //#endregion
}
