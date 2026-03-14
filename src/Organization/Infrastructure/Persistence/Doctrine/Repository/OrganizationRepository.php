<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\OrganizationId;
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;
use function is_array;

/**
 * Repository OrganizationRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationRepository implements OrganizationRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(OrganizationRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization aggregate.
   *
   * @since 1.0.0
   *
   * @param Organization $organization the organization aggregate
   */
  public function save(Organization $organization): void
  {
    $record = OrganizationMapper::toRecord($organization);
    $existing = $this->repository->find($record->id);

    if ($existing instanceof OrganizationRecord) {
      $existing->name = $record->name;
      $existing->slug = $record->slug;
      $existing->ownerUserId = $record->ownerUserId;
      $existing->createdByUserId = $record->createdByUserId;
      $existing->status = $record->status;
      $existing->isActive = $record->isActive;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Finds an organization by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   *
   * @return ?Organization the organization aggregate when found
   */
  public function findById(OrganizationId $id): ?Organization
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof OrganizationRecord) {
      return null;
    }

    return OrganizationMapper::toDomain($record);
  }

  /**
   * Method findByIds.
   *
   * Finds organizations for a list of identifiers.
   *
   * @since 1.0.0
   *
   * @param list<OrganizationId> $ids the organization identifiers
   *
   * @return list<Organization> the matching organizations
   */
  public function findByIds(array $ids): array
  {
    if ([] === $ids) {
      return [];
    }

    $rawIds = array_map(static fn (OrganizationId $id): string => (string) $id, $ids);

    $records = $this->repository->createQueryBuilder('o')
      ->where('o.id IN (:ids)')
      ->setParameter('ids', $rawIds)
      ->orderBy('o.name', 'ASC')
      ->getQuery()
      ->getResult();

    if (!is_array($records)) {
      return [];
    }

    $organizations = [];
    foreach ($records as $record) {
      if ($record instanceof OrganizationRecord) {
        $organizations[] = OrganizationMapper::toDomain($record);
      }
    }

    return $organizations;
  }

  /**
   * Method findAll.
   *
   * Returns every organization sorted by name.
   *
   * @since 1.0.0
   *
   * @return list<Organization> the organizations collection
   */
  public function findAll(): array
  {
    $records = $this->repository->findBy([], ['name' => 'ASC']);

    return array_map(
      static fn (OrganizationRecord $record): Organization => OrganizationMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method delete.
   *
   * Deletes an organization by identifier.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $id the organization identifier
   */
  public function delete(OrganizationId $id): void
  {
    $record = $this->repository->find((string) $id);

    if ($record instanceof OrganizationRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  // #endregion
}
