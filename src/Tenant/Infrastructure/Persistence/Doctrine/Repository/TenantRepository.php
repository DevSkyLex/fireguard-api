<?php

declare(strict_types=1);

namespace Tenant\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Tenant\Application\Port\Outbound\TenantRepositoryPort;
use Tenant\Domain\Model\Tenant;
use Tenant\Domain\ValueObject\TenantId;
use Tenant\Infrastructure\Persistence\Doctrine\Mapper\TenantMapper;
use Tenant\Infrastructure\Persistence\Doctrine\Record\TenantRecord;

use function array_map;

/**
 * Repository TenantRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class TenantRepository implements TenantRepositoryPort
{
  // #region Properties
  /**
   * Property repository.
   *
   * The Doctrine entity repository.
   *
   * @since 1.0.0
   *
   * @var EntityRepository<TenantRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->repository = $entityManager->getRepository(className: TenantRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save
   * {@inheritDoc}
   */
  public function save(Tenant $tenant): void
  {
    $record = TenantMapper::toRecord(tenant: $tenant);
    $existingRecord = $this->repository->find(id: $record->id);

    if ($existingRecord) {
      $existingRecord->name = $record->name;
      $existingRecord->settings = $record->settings;
      $existingRecord->isActive = $record->isActive;
    } else {
      $this->entityManager->persist(object: $record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById
   * {@inheritDoc}
   */
  public function findById(TenantId $id): ?Tenant
  {
    $record = $this->repository->find(id: (string) $id);

    if (!$record) {
      return null;
    }

    return TenantMapper::toDomain(record: $record);
  }

  /**
   * Method findAll
   * {@inheritDoc}
   */
  public function findAll(): array
  {
    $records = $this->repository->findBy(
      criteria: ['isActive' => true],
      orderBy: ['name' => 'ASC']
    );

    return array_map(
      callback: fn (TenantRecord $record): Tenant => TenantMapper::toDomain(record: $record),
      array: $records
    );
  }

  /**
   * Method delete
   * {@inheritDoc}
   */
  public function delete(TenantId $id): void
  {
    $record = $this->repository->find(id: (string) $id);

    if ($record) {
      $this->entityManager->remove(object: $record);
      $this->entityManager->flush();
    }
  }
  // #endregion
}
