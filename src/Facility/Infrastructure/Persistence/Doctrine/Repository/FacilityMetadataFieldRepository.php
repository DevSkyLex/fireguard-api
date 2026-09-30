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
  /**
   * Method __construct
   *
   * Creates a repository for persisted facility metadata definitions on the main database.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager the main entity manager
   *
   * @return void
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityMetadataFieldRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Creates or updates the persisted definition from the domain field.
   *
   * @access public
   *
   * @param FacilityMetadataField $field the metadata field aggregate
   *
   * @return void
   */
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

  /**
   * Method delete.
   *
   * Removes a field definition without rewriting facility metadata values.
   *
   * @access public
   *
   * @param FacilityMetadataFieldId $id the metadata field identifier
   *
   * @return void
   */
  public function delete(FacilityMetadataFieldId $id): void
  {
    $record = $this->repository->find((string) $id);
    if (!$record instanceof FacilityMetadataFieldRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }

  /**
   * Method findById
   *
   * Looks up a metadata definition by its domain identifier and maps a matching record.
   *
   * @access public
   *
   * @param FacilityMetadataFieldId $id the metadata field identifier
   *
   * @return ?FacilityMetadataField the field when it exists
   */
  public function findById(FacilityMetadataFieldId $id): ?FacilityMetadataField
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityMetadataFieldRecord) {
      return null;
    }

    return FacilityMetadataFieldMapper::toDomain($record);
  }

  /**
   * Method findByOrganizationIdAndKey
   *
   * Finds a metadata definition by machine key within its owning organization.
   *
   * @access public
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   * @param string $key the machine key
   *
   * @return ?FacilityMetadataField the matching field when it exists
   */
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

  /**
   * Method findByOrganizationId.
   *
   * Lists definitions in label order for a stable form-schema listing.
   *
   * @access public
   * @access public
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return list<FacilityMetadataField> the organization's field definitions
   */
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

  /**
   * Method countByOrganizationId
   *
   * Counts metadata definitions belonging to one organization.
   *
   * @access public
   *
   * @param FacilityOrganizationId $organizationId the organization identifier
   *
   * @return int the number of field definitions
   */
  public function countByOrganizationId(FacilityOrganizationId $organizationId): int
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);

    return (int) $this->repository->count(['organization' => $organization]);
  }
  // #endregion
}
