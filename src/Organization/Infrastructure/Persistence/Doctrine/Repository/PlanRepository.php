<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\PlanRepositoryPort;
use Organization\Domain\Model\Plan\Plan;
use Organization\Domain\ValueObject\{PlanId, PlanKey};
use Organization\Infrastructure\Persistence\Doctrine\Mapper\PlanMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\PlanRecord;

use function array_map;
use function array_values;

/**
 * Repository PlanRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class PlanRepository implements PlanRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<PlanRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the PlanRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(PlanRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the plan aggregate.
   *
   * @since 1.0.0
   *
   * @param Plan $plan the plan aggregate
   */
  public function save(Plan $plan): void
  {
    $record = PlanMapper::toRecord($plan);
    $existing = $this->repository->find($record->id);

    if ($existing instanceof PlanRecord) {
      $existing->key = $record->key;
      $existing->name = $record->name;
      $existing->description = $record->description;
      $existing->limits = $record->limits;
      $existing->isActive = $record->isActive;
      $existing->isDefault = $record->isDefault;
      $existing->sortOrder = $record->sortOrder;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Finds a plan by identifier.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   *
   * @return ?Plan the plan aggregate when found
   */
  public function findById(PlanId $id): ?Plan
  {
    $record = $this->repository->find((string) $id);

    return $record instanceof PlanRecord ? PlanMapper::toDomain($record) : null;
  }

  /**
   * Method findByKey.
   *
   * Finds a plan by its stable machine key.
   *
   * @since 1.0.0
   *
   * @param PlanKey $key the plan key
   *
   * @return ?Plan the plan aggregate when found
   */
  public function findByKey(PlanKey $key): ?Plan
  {
    $record = $this->repository->findOneBy(['key' => (string) $key]);

    return $record instanceof PlanRecord ? PlanMapper::toDomain($record) : null;
  }

  /**
   * Method findDefault.
   *
   * Finds the catalog default plan.
   *
   * @since 1.0.0
   *
   * @return ?Plan the default plan when configured
   */
  public function findDefault(): ?Plan
  {
    $record = $this->repository->findOneBy(['isDefault' => true], ['sortOrder' => 'ASC']);

    return $record instanceof PlanRecord ? PlanMapper::toDomain($record) : null;
  }

  /**
   * Method findAll.
   *
   * Returns every plan ordered by display order.
   *
   * @since 1.0.0
   *
   * @return list<Plan> the plans collection
   */
  public function findAll(): array
  {
    return $this->mapRecords($this->repository->findBy([], ['sortOrder' => 'ASC', 'name' => 'ASC']));
  }

  /**
   * Method findAllActive.
   *
   * Returns every selectable plan ordered by display order.
   *
   * @since 1.0.0
   *
   * @return list<Plan> the active plans collection
   */
  public function findAllActive(): array
  {
    return $this->mapRecords($this->repository->findBy(['isActive' => true], ['sortOrder' => 'ASC', 'name' => 'ASC']));
  }

  /**
   * Method delete.
   *
   * Deletes a plan by identifier.
   *
   * @since 1.0.0
   *
   * @param PlanId $id the plan identifier
   */
  public function delete(PlanId $id): void
  {
    $record = $this->repository->find((string) $id);

    if ($record instanceof PlanRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }

  /**
   * Method mapRecords.
   *
   * Maps a list of plan records to domain aggregates.
   *
   * @since 1.0.0
   *
   * @param array<int, PlanRecord> $records the persistence records
   *
   * @return list<Plan> the domain aggregates
   */
  private function mapRecords(array $records): array
  {
    return array_values(array_map(
      static fn (PlanRecord $record): Plan => PlanMapper::toDomain($record),
      $records,
    ));
  }
  // #endregion
}
