<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;

/**
 * Repository FacilityRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityRepository implements FacilityRepositoryPort
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
   * Initializes a new instance of the FacilityRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the facility aggregate.
   *
   * @since 1.0.0
   *
   * @param Facility $facility the facility aggregate
   */
  public function save(Facility $facility): void
  {
    $record = FacilityMapper::toRecord($facility);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $facility->organizationId());
    $record->organization = $organization;
    $record->parentFacility = null !== $facility->parentFacilityId()
      ? $this->entityManager->getReference(FacilityRecord::class, (string) $facility->parentFacilityId())
      : null;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof FacilityRecord) {
      $existing->organization = $organization;
      $existing->parentFacility = $record->parentFacility;
      $existing->type = $record->type;
      $existing->name = $record->name;
      $existing->code = $record->code;
      $existing->status = $record->status;
      $existing->address = $record->address;
      $existing->metadata = $record->metadata;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * Finds a facility by identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityId $id the facility identifier
   *
   * @return ?Facility the facility aggregate when found
   */
  public function findById(FacilityId $id): ?Facility
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityRecord) {
      return null;
    }

    return FacilityMapper::toDomain($record);
  }

  /**
   * Method countByOrganizationId.
   *
   * Counts facilities belonging to an organization.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the facility count
   */
  public function countByOrganizationId(FacilityOrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count([
      'organization' => $organization,
    ]);
  }

  /**
   * Method findByOrganizationId.
   *
   * Lists facilities by organization identifier.
   *
   * @since 1.0.0
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return list<Facility> the facilities
   */
  public function findByOrganizationId(FacilityOrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $records = $this->repository->findBy(
      ['organization' => $organization],
      ['name' => 'ASC'],
    );

    return array_map(
      static fn (FacilityRecord $record): Facility => FacilityMapper::toDomain($record),
      $records,
    );
  }
  // #endregion
}
