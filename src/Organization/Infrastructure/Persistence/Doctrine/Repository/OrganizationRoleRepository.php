<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\OrganizationRoleRepositoryPort;
use Organization\Domain\Model\OrganizationRole\OrganizationRole;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationRoleId, OrganizationRoleName};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationRoleMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRoleRecord;

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
    $record = OrganizationRoleMapper::toRecord($role);
    $existing = $this->repository->find($record->id);

    if ($existing instanceof OrganizationRoleRecord) {
      $existing->name = $record->name;
      $existing->permissions = $record->permissions;
      $existing->isSystem = $record->isSystem;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
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

    return OrganizationRoleMapper::toDomain($record);
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
    $record = $this->repository->findOneBy([
      'organizationId' => (string) $organizationId,
      'name' => (string) $name,
    ]);

    if (!$record instanceof OrganizationRoleRecord) {
      return null;
    }

    return OrganizationRoleMapper::toDomain($record);
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
    $records = $this->repository->findBy([
      'organizationId' => (string) $organizationId,
    ], [
      'name' => 'ASC',
    ]);

    return array_map(
      static fn (OrganizationRoleRecord $record): OrganizationRole => OrganizationRoleMapper::toDomain($record),
      $records,
    );
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
      ->where('r.organizationId = :organizationId')
      ->andWhere('r.id IN (:ids)')
      ->setParameter('organizationId', (string) $organizationId)
      ->setParameter('ids', $rawIds)
      ->getQuery()
      ->getResult();

    if (!is_array($records)) {
      return [];
    }

    $roles = [];
    foreach ($records as $record) {
      if ($record instanceof OrganizationRoleRecord) {
        $roles[] = OrganizationRoleMapper::toDomain($record);
      }
    }

    return $roles;
  }
  // #endregion
}
