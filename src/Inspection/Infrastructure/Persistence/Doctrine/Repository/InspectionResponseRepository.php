<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\InspectionResponseRepositoryPort;
use Inspection\Domain\Model\Response\InspectionResponse;
use Inspection\Domain\ValueObject\InspectionResponseId;
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionResponseMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionResponseRecord;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;

/**
 * Repository InspectionResponseRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionResponseRepository implements InspectionResponseRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<InspectionResponseRecord>
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
    $this->repository = $this->entityManager->getRepository(InspectionResponseRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(InspectionResponse $response): void
  {
    $record = $this->repository->find((string) $response->id());
    $isNew = !$record instanceof InspectionResponseRecord;
    $record = $isNew ? new InspectionResponseRecord() : $record;

    InspectionResponseMapper::applyTo($response, $record);
    /** @var OrganizationRecord $organization */
    $organization = $this->entityManager->getReference(OrganizationRecord::class, (string) $response->organizationId());
    $record->organization = $organization;

    if ($isNew) {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(InspectionResponseId $id): ?InspectionResponse
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionResponseRecord) {
      return null;
    }

    return InspectionResponseMapper::toDomain($record);
  }

  /**
   * Method existsByClientId.
   *
   * @since 1.0.0
   */
  public function existsByClientId(string $clientId): bool
  {
    return $this->repository->count(['clientId' => $clientId]) > 0;
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(InspectionResponseId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionResponseRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
