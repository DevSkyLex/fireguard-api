<?php

declare(strict_types=1);

namespace Facility\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Outbound\FacilityAttachmentRepositoryPort;
use Facility\Domain\Model\Attachment\FacilityAttachment;
use Facility\Domain\ValueObject\{AttachmentKind, FacilityAttachmentId, FacilityId};
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
      $existing->kind = $record->kind;
      $existing->isPrimaryPlan = $record->isPrimaryPlan;
      $existing->imageWidth = $record->imageWidth;
      $existing->imageHeight = $record->imageHeight;
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
  public function findByFacilityId(FacilityId $facilityId, ?AttachmentKind $kind = null): array
  {
    /** @var FacilityRecord $facility */
    $facility = $this->entityManager->getReference(FacilityRecord::class, (string) $facilityId);
    $criteria = ['facility' => $facility];
    if (null !== $kind) {
      $criteria['kind'] = $kind->value;
    }
    $records = $this->repository->findBy(
      $criteria,
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

  /**
   * Method clearPrimaryPlan.
   *
   * @since 1.1.0
   */
  public function clearPrimaryPlan(FacilityId $facilityId, FacilityAttachmentId $exceptAttachmentId): void
  {
    $this->entityManager->createQueryBuilder()
      ->update(FacilityAttachmentRecord::class, 'attachment')
      ->set('attachment.isPrimaryPlan', ':cleared')
      ->where('attachment.facility = :facilityId')
      ->andWhere('attachment.id != :exceptId')
      ->setParameter('cleared', false)
      ->setParameter('facilityId', (string) $facilityId)
      ->setParameter('exceptId', (string) $exceptAttachmentId)
      ->getQuery()
      ->execute();
  }

  /**
   * Method findPrimaryFloorPlan.
   *
   * @since 1.2.0
   */
  public function findPrimaryFloorPlan(FacilityId $facilityId): ?FacilityAttachment
  {
    /** @var FacilityRecord $facility */
    $facility = $this->entityManager->getReference(FacilityRecord::class, (string) $facilityId);

    $record = $this->repository->findOneBy([
      'facility' => $facility,
      'kind' => AttachmentKind::FLOOR_PLAN->value,
      'isPrimaryPlan' => true,
    ]);

    if (!$record instanceof FacilityAttachmentRecord) {
      return null;
    }

    return FacilityAttachmentMapper::toDomain($record);
  }
  // #endregion
}
