<?php

declare(strict_types=1);

namespace Organization\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Organization\Application\Port\Outbound\OrganizationLegalProfileRepositoryPort;
use Organization\Domain\Model\OrganizationLegalProfile\OrganizationLegalProfile;
use Organization\Domain\ValueObject\OrganizationId;
use Organization\Infrastructure\Persistence\Doctrine\Mapper\OrganizationLegalProfileMapper;
use Organization\Infrastructure\Persistence\Doctrine\Record\{OrganizationLegalProfileRecord, OrganizationRecord};

/**
 * Repository OrganizationLegalProfileRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class OrganizationLegalProfileRepository implements OrganizationLegalProfileRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<OrganizationLegalProfileRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * Initializes a new instance of the OrganizationLegalProfileRepository class.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the Doctrine entity manager
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(OrganizationLegalProfileRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * Persists the organization legal profile aggregate.
   *
   * @since 1.0.0
   *
   * @param OrganizationLegalProfile $profile the legal profile aggregate
   */
  public function save(OrganizationLegalProfile $profile): void
  {
    $record = OrganizationLegalProfileMapper::toRecord($profile);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $profile->organizationId());
    $record->organization = $organization;
    $existing = $this->repository->findOneBy([
      'organization' => $organization,
    ]);

    if ($existing instanceof OrganizationLegalProfileRecord) {
      $existing->organization = $organization;
      $existing->countryCode = $record->countryCode;
      $existing->legalType = $record->legalType;
      $existing->legalName = $record->legalName;
      $existing->registrationNumber = $record->registrationNumber;
      $existing->vatNumber = $record->vatNumber;
      $existing->updatedAt = $record->updatedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findByOrganizationId.
   *
   * Finds the legal profile for a given organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   *
   * @return ?OrganizationLegalProfile the legal profile when found
   */
  public function findByOrganizationId(OrganizationId $organizationId): ?OrganizationLegalProfile
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->repository->findOneBy([
      'organization' => $organization,
    ]);

    if (!$record instanceof OrganizationLegalProfileRecord) {
      return null;
    }

    return OrganizationLegalProfileMapper::toDomain($record);
  }

  /**
   * Method deleteByOrganizationId.
   *
   * Deletes the legal profile attached to an organization.
   *
   * @since 1.0.0
   *
   * @param OrganizationId $organizationId the organization identifier
   */
  public function deleteByOrganizationId(OrganizationId $organizationId): void
  {
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $organizationId);
    $record = $this->repository->findOneBy([
      'organization' => $organization,
    ]);

    if ($record instanceof OrganizationLegalProfileRecord) {
      $this->entityManager->remove($record);
      $this->entityManager->flush();
    }
  }
  // #endregion
}
