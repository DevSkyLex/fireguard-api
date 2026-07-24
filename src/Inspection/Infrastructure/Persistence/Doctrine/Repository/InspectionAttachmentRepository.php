<?php

declare(strict_types=1);

namespace Inspection\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Inspection\Application\Port\Outbound\InspectionAttachmentRepositoryPort;
use Inspection\Domain\Model\Attachment\InspectionAttachment;
use Inspection\Domain\ValueObject\{InspectionAttachmentId, InspectionId, NonConformityId};
use Inspection\Infrastructure\Persistence\Doctrine\Mapper\InspectionAttachmentMapper;
use Inspection\Infrastructure\Persistence\Doctrine\Record\{InspectionAttachmentRecord, InspectionRecord, NonConformityRecord};

use function array_map;

/**
 * Repository InspectionAttachmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InspectionAttachmentRepository implements InspectionAttachmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<InspectionAttachmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(InspectionAttachmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(InspectionAttachment $attachment): void
  {
    $record = InspectionAttachmentMapper::toRecord($attachment);
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $attachment->inspectionId());
    $record->inspection = $inspection;
    $record->nonConformity = null === $attachment->nonConformityId()
      ? null
      : $this->entityManager->getReference(NonConformityRecord::class, (string) $attachment->nonConformityId());
    $existing = $this->repository->find($record->id);

    if ($existing instanceof InspectionAttachmentRecord) {
      $existing->inspection = $inspection;
      $existing->nonConformity = $record->nonConformity;
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
  public function findById(InspectionAttachmentId $id): ?InspectionAttachment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionAttachmentRecord) {
      return null;
    }

    return InspectionAttachmentMapper::toDomain($record);
  }

  /**
   * Method findByInspectionId.
   *
   * @since 1.0.0
   */
  public function findByInspectionId(InspectionId $inspectionId): array
  {
    /** @var InspectionRecord $inspection */
    $inspection = $this->entityManager->getReference(InspectionRecord::class, (string) $inspectionId);
    $records = $this->repository->findBy(
      ['inspection' => $inspection, 'nonConformity' => null],
      ['uploadedAt' => 'DESC'],
    );

    return array_map(
      static fn (InspectionAttachmentRecord $record): InspectionAttachment => InspectionAttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method findByNonConformityId.
   *
   * @since 1.0.0
   */
  public function findByNonConformityId(NonConformityId $nonConformityId): array
  {
    /** @var NonConformityRecord $nonConformity */
    $nonConformity = $this->entityManager->getReference(NonConformityRecord::class, (string) $nonConformityId);
    $records = $this->repository->findBy(
      ['nonConformity' => $nonConformity],
      ['uploadedAt' => 'DESC'],
    );

    return array_map(
      static fn (InspectionAttachmentRecord $record): InspectionAttachment => InspectionAttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(InspectionAttachmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InspectionAttachmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
