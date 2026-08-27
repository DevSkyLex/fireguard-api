<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\AttachmentRepositoryPort;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\AttachmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};

use function array_map;

/**
 * Repository AttachmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AttachmentRepository implements AttachmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<EquipmentAttachmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(EquipmentAttachmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(EquipmentAttachment $attachment): void
  {
    $record = AttachmentMapper::toRecord($attachment);
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $attachment->equipmentId());
    $record->equipment = $equipment;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof EquipmentAttachmentRecord) {
      $existing->equipment = $equipment;
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
  public function findById(AttachmentId $id): ?EquipmentAttachment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentAttachmentRecord) {
      return null;
    }

    return AttachmentMapper::toDomain($record);
  }

  /**
   * Method findByEquipmentId.
   *
   * @since 1.0.0
   */
  public function findByEquipmentId(EquipmentId $equipmentId): array
  {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);
    $records = $this->repository->findBy(
      ['equipment' => $equipment],
      ['uploadedAt' => 'DESC'],
    );

    return array_map(
      static fn (EquipmentAttachmentRecord $record): EquipmentAttachment => AttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByEquipmentId.
   *
   * @since 1.0.0
   */
  public function countByEquipmentId(EquipmentId $equipmentId): int
  {
    /** @var EquipmentRecord $equipment */
    $equipment = $this->entityManager->getReference(EquipmentRecord::class, (string) $equipmentId);

    return $this->repository->count(['equipment' => $equipment]);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(AttachmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof EquipmentAttachmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
