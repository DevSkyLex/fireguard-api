<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Application\Service\OrganizationCacheInvalidator;
use Organization\Domain\Catalog\OrganizationSystemRoleCatalog;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationRoleMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationMemberRoleRecord, OrganizationRecord, OrganizationRoleRecord};

use function array_filter;
use function array_map;
use function is_array;

/**
 * Repository OrganizationRoleRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRoleRepository implements OrganizationRoleRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationRoleRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationRoleRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ?OrganizationCacheInvalidator $cacheInvalidator = null,
  ) {
    $this->repository = $entityManager->getRepository(OrganizationRoleRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization role aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the role aggregate
   */
  public function save(OrganizationRole $role): void
  {
    $assignedProfiles = null !== $this->cacheInvalidator ? $this->findAssignedMemberProfiles($role->id()) : [];

    $record = OrganizationRoleMapper::toRecord($role);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $role->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof OrganizationRoleRecord) {
      $existing->organization = $organization;
      $existing->name = $record->name;
      $existing->permissions = $record->permissions;
      $existing->description = $record->description;
      $existing->isSystem = $record->isSystem;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
    $this->cacheInvalidator?->invalidateCurrentMemberProfiles($assignedProfiles);
  }

  /**
   * Method findById.
   *
   * Finds a role by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationRoleId $id the role identifier
   *
   * @return ?OrganizationRole the role aggregate when found
   */
  public function findById(OrganizationRoleId $id): ?OrganizationRole
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof OrganizationRoleRecord) {
      return null;
    }

    return $this->normalizeSystemRolePermissions(OrganizationRoleMapper::toDomain($record));
  }

  /**
   * Method findByOrganizationAndName.
   *
   * Finds a role by organization identifier and role name.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param OrganizationRoleName $name the role name
   *
   * @return ?OrganizationRole the role aggregate when found
   */
  public function findByOrganizationAndName(OrganizationId $organizationId, OrganizationRoleName $name): ?OrganizationRole
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->repository->findOneBy([
      'organization' => $organization,
      'name' => (string) $name,
    ]);

    if (!$record instanceof OrganizationRoleRecord) {
      return null;
    }

    return $this->normalizeSystemRolePermissions(OrganizationRoleMapper::toDomain($record));
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists roles belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return list<OrganizationRole> the organization roles
   */
  public function findByOrganizationId(OrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $records = $this->repository->findBy([
      'organization' => $organization,
    ], [
      'name' => 'ASC',
    ]);

    return array_map(
      fn (OrganizationRoleRecord $record): OrganizationRole => $this->normalizeSystemRolePermissions(OrganizationRoleMapper::toDomain($record)),
      $records,
    );
  }

  /**
   * Method countByOrganizationId.
   *
   * Counts roles belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return int the role count
   */
  public function countByOrganizationId(OrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count([
      'organization' => $organization,
    ]);
  }

  public function countSystemByOrganizationId(OrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count([
      'organization' => $organization,
      'isSystem' => true,
    ]);
  }

  /**
   * Method findByIdsInOrganization.
   *
   * Finds organization roles by a scoped list of role identifiers.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   * @param list<OrganizationRoleId> $roleIds the role identifiers
   *
   * @return list<OrganizationRole> the matching roles
   */
  public function findByIdsInOrganization(OrganizationId $organizationId, array $roleIds): array
  {
    if ([] === $roleIds) {
      return [];
    }

    $rawIds = array_map(static fn (OrganizationRoleId $id): string => (string) $id, $roleIds);

    $records = $this->repository->createQueryBuilder('r')
      ->where('r.organization = :organization')
      ->andWhere('r.id IN (:ids)')
      ->setParameter('organization', $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId))
      ->setParameter('ids', $rawIds)
      ->getQuery()
      ->getResult();

    if (!is_array($records)) {
      return [];
    }

    $roles = [];
    foreach ($records as $record) {
      if ($record instanceof OrganizationRoleRecord) {
        $roles[] = $this->normalizeSystemRolePermissions(OrganizationRoleMapper::toDomain($record));
      }
    }

    return $roles;
  }

  /**
   * Method remove.
   *
   * Deletes an organization role and cascades member role assignments.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the role aggregate to delete
   */
  public function remove(OrganizationRole $role): void
  {
    $assignedProfiles = null !== $this->cacheInvalidator ? $this->findAssignedMemberProfiles($role->id()) : [];

    $record = $this->repository->find((string) $role->id());

    if ($record instanceof OrganizationRoleRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
      $this->cacheInvalidator?->invalidateCurrentMemberProfiles($assignedProfiles);
    }
  }

  /**
   * @return list<array{organizationId: string, userId: string}>
   */
  private function findAssignedMemberProfiles(OrganizationRoleId $roleId): array
  {
    $roleRecord = $this->repository->find((string) $roleId);
    if (!$roleRecord instanceof OrganizationRoleRecord) {
      return [];
    }

    $records = $this->entityManager
      ->getRepository(OrganizationMemberRoleRecord::class)
      ->findBy(['role' => $roleRecord]);

    return array_map(
      static fn (OrganizationMemberRoleRecord $record): array => [
        'organizationId' => (string) $record->member?->organization?->id,
        'userId' => (string) $record->member?->userId,
      ],
      array_filter(
        $records,
        static fn ($record): bool => $record instanceof OrganizationMemberRoleRecord
          && null !== $record->member?->organization
          && '' !== $record->member->userId,
      ),
    );
  }

  /**
   * Method normalizeSystemRolePermissions.
   *
   * Ensures system roles expose the current canonical permission set.
   *
   * @since 1.0.0
   *
   * @param OrganizationRole $role the role aggregate
   *
   * @return OrganizationRole the normalized role aggregate
   */
  private function normalizeSystemRolePermissions(OrganizationRole $role): OrganizationRole
  {
    $role->updatePermissions(OrganizationSystemRoleCatalog::mergePermissions(
      roleName: (string) $role->name(),
      permissions: $role->permissions(),
      isSystem: $role->isSystem(),
    ));

    return $role;
  }
  // #endregion
}
