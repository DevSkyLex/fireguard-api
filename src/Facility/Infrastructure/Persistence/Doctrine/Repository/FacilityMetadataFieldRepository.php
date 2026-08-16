<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Outbound\FacilityMetadataFieldRepositoryPort;
use Facility\Domain\Model\MetadataField\FacilityMetadataField;
use Facility\Domain\ValueObject\{FacilityMetadataFieldId, FacilityOrganizationId};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityMetadataFieldMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityMetadataFieldRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

use function array_map;

/**
 * Repository FacilityMetadataFieldRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityMetadataFieldRepository implements FacilityMetadataFieldRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<FacilityMetadataFieldRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityMetadataFieldRecord::class);
  }
  // #endregion

  // #region Methods
  public function save(FacilityMetadataField $field): void
  {
    $record = FacilityMetadataFieldMapper::toRecord($field);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $field->organizationId());
    $record->organization = $organization;

    $existing = $this->repository->find($record->id);

    if ($existing instanceof FacilityMetadataFieldRecord) {
      $existing->organization = $organization;
      $existing->key = $record->key;
      $existing->label = $record->label;
      $existing->fieldType = $record->fieldType;
      $existing->options = $record->options;
      $existing->facilityType = $record->facilityType;
      $existing->required = $record->required;
      $existing->unit = $record->unit;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function delete(FacilityMetadataFieldId $id): void
  {
    $record = $this->repository->find((string) $id);
    if (!$record instanceof FacilityMetadataFieldRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  public function findById(FacilityMetadataFieldId $id): ?FacilityMetadataField
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityMetadataFieldRecord) {
      return null;
    }

    return FacilityMetadataFieldMapper::toDomain($record);
  }

  public function findByOrganizationIdAndKey(FacilityOrganizationId $organizationId, string $key): ?FacilityMetadataField
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    $record = $this->repository->findOneBy(['organization' => $organization, 'key' => $key]);

    if (!$record instanceof FacilityMetadataFieldRecord) {
      return null;
    }

    return FacilityMetadataFieldMapper::toDomain($record);
  }

  public function findByOrganizationId(FacilityOrganizationId $organizationId): array
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    /** @var list<FacilityMetadataFieldRecord> $records */
    $records = $this->repository->findBy(['organization' => $organization], ['label' => 'ASC']);

    return array_map(
      static fn (FacilityMetadataFieldRecord $record): FacilityMetadataField => FacilityMetadataFieldMapper::toDomain($record),
      $records,
    );
  }

  public function countByOrganizationId(FacilityOrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count(['organization' => $organization]);
  }
  // #endregion
}
