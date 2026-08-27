<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Outbound\CanonicalFacilityRepositoryPort;
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\FacilityId;
use Facility\Infrastructure\Persistence\Doctrine\Mapper\CanonicalFacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;

use function in_array;

/**
 * Repository CanonicalFacilityRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class CanonicalFacilityRepository implements CanonicalFacilityRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<FacilityRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the `main` entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(FacilityId $id): ?CanonicalFacility
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return null;
    }

    return CanonicalFacilityMapper::toDomain($record);
  }

  /**
   * Method save.
   *
   * The `parentFacility` association is resolved here rather than in the
   * mapper: turning an identifier into a reference needs an entity manager.
   *
   * @since 1.0.0
   */
  public function save(CanonicalFacility $facility): void
  {
    $record = $this->repository->find((string) $facility->id());

    if (!$record instanceof FacilityRecord) {
      return;
    }

    CanonicalFacilityMapper::applyTo($facility, $record);

    $parentFacilityId = $facility->parentFacilityId();
    if ($parentFacilityId !== $record->parentFacility?->id) {
      /** @var ?FacilityRecord $parent */
      $parent = null === $parentFacilityId
        ? null
        : $this->entityManager->getReference(FacilityRecord::class, $parentFacilityId);
      $record->parentFacility = $parent;
    }

    $this->entityManager->flush();
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(FacilityId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method countChildren.
   *
   * @since 1.0.0
   */
  public function countChildren(FacilityId $id): int
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return 0;
    }

    return $this->repository->count(['parentFacility' => $record]);
  }

  /**
   * Method ancestorIdsOf.
   *
   * Walks the parent chain in memory. `parentFacility` is a `ManyToOne` on an
   * already-managed record, so each step is an identity-map hit or a single
   * primary-key read — and the chain is bounded by the configured depth cap.
   *
   * The visited set is not paranoia: a cycle already in the table would
   * otherwise spin here forever, and this method is the very guard that
   * prevents new ones.
   *
   * @since 1.0.0
   *
   * @return list<string> the ancestor identifiers, nearest first
   */
  public function ancestorIdsOf(FacilityId $id): array
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return [];
    }

    $ancestors = [];
    $current = $record->parentFacility;

    while ($current instanceof FacilityRecord) {
      if (in_array($current->id, $ancestors, true)) {
        break;
      }

      $ancestors[] = $current->id;
      $current = $current->parentFacility;
    }

    return $ancestors;
  }
  // #endregion
}
