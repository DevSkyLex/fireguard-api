<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Outbound\FacilityAttachmentRepositoryPort;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{FacilityAttachmentId, FacilityId};
use Facility\Infrastructure\Persistence\Doctrine\Mapper\FacilityAttachmentMapper;
use Facility\Infrastructure\Persistence\Doctrine\Record\{FacilityAttachmentRecord, FacilityRecord};

use function array_map;

/**
 * Repository FacilityAttachmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class FacilityAttachmentRepository implements FacilityAttachmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<FacilityAttachmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(FacilityAttachmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(FacilityAttachment $attachment): void
  {
    $record = FacilityAttachmentMapper::toRecord($attachment);
    /** @var FacilityRecord $facility */
    $facility = $this->entityManager->getReference(FacilityRecord::class, (string) $attachment->facilityId());
    $record->facility = $facility;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof FacilityAttachmentRecord) {
      $existing->facility = $facility;
      $existing->fileName = $record->fileName;
      $existing->storagePath = $record->storagePath;
      $existing->mimeType = $record->mimeType;
      $existing->size = $record->size;
      $existing->label = $record->label;
      $existing->uploadedAt = $record->uploadedAt;
    } else {
      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  /**
   * Method findById.
   *
   * @since 1.0.0
   */
  public function findById(FacilityAttachmentId $id): ?FacilityAttachment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityAttachmentRecord) {
      return null;
    }

    return FacilityAttachmentMapper::toDomain($record);
  }

  /**
   * Method findByFacilityId.
   *
   * @since 1.0.0
   */
  public function findByFacilityId(FacilityId $facilityId): array
  {
    /** @var FacilityRecord $facility */
    $facility = $this->entityManager->getReference(FacilityRecord::class, (string) $facilityId);
    $records = $this->repository->findBy(
      ['facility' => $facility],
      ['uploadedAt' => 'DESC'],
    );

    return array_map(
      static fn (FacilityAttachmentRecord $record): FacilityAttachment => FacilityAttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByFacilityId.
   *
   * @since 1.0.0
   */
  public function countByFacilityId(FacilityId $facilityId): int
  {
    /** @var FacilityRecord $facility */
    $facility = $this->entityManager->getReference(FacilityRecord::class, (string) $facilityId);

    return $this->repository->count(['facility' => $facility]);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(FacilityAttachmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof FacilityAttachmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
