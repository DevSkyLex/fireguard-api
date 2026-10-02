<?php

declare(strict_types=1);

namespace Equipment\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Equipment\Application\Port\Outbound\AttachmentRepositoryPort;
use Equipment\Domain\Exception\AttachmentNotFoundException;
use Equipment\Domain\Model\Attachment\EquipmentAttachment;
use Equipment\Domain\ValueObject\{AttachmentId, EquipmentId, RestoredEquipmentAttachmentFile};
use Equipment\Infrastructure\Persistence\Doctrine\Mapper\AttachmentMapper;
use Equipment\Infrastructure\Persistence\Doctrine\Record\{EquipmentAttachmentRecord, EquipmentRecord};
use Shared\Domain\Attachment\AttachmentConstraints;

use function array_map;

/**
 * Repository AttachmentRepository.
 *
 * @category Repository
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
  /**
   * Method __construct
   *
   * Receives the entity manager used to access equipment attachment records.
   *
   * @access public
   *
   * @param EntityManagerInterface $entityManager Doctrine entity manager used to access attachment persistence
   *
   * @return void
   */
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
   * Persists the first attempt without overwriting an upload already acknowledged.
   *
   * @param EquipmentAttachment $attachment upload attempt
   *
   * @return EquipmentAttachment stored upload
   */
  public function saveIfAbsent(EquipmentAttachment $attachment): EquipmentAttachment
  {
    $connection = $this->entityManager->getConnection();

    return $connection->transactional(function () use ($connection, $attachment): EquipmentAttachment {
      $connection->executeStatement('SELECT id FROM equipment WHERE id = :id FOR UPDATE', ['id' => (string) $attachment->equipmentId()]);
      $row = $connection->fetchAssociative('SELECT * FROM equipment_attachments WHERE id = :id', ['id' => (string) $attachment->id()]);
      if (false === $row) {
        /** @var int|string $count */
        $count = $connection->fetchOne('SELECT COUNT(*) FROM equipment_attachments WHERE equipment_id = :id', ['id' => (string) $attachment->equipmentId()]);
        AttachmentConstraints::validateCount((int) $count);
        $connection->executeStatement(
          'INSERT INTO equipment_attachments (id, equipment_id, file_name, storage_path, mime_type, size, label, uploaded_at)
           VALUES (:id, :equipment, :name, :path, :mime, :size, :label, :uploaded) ON CONFLICT (id) DO NOTHING',
          ['id' => (string) $attachment->id(), 'equipment' => (string) $attachment->equipmentId(), 'name' => $attachment->fileName(),
            'path' => $attachment->storagePath(), 'mime' => $attachment->mimeType(), 'size' => $attachment->size(),
            'label' => $attachment->label(), 'uploaded' => $attachment->uploadedAt()->format('Y-m-d H:i:s')],
        );
        $row = $connection->fetchAssociative('SELECT * FROM equipment_attachments WHERE id = :id', ['id' => (string) $attachment->id()]);
      }
      /** @var array{id: string, equipment_id: string, file_name: string, storage_path: string, mime_type: string, size: int|string, label: ?string, uploaded_at: string}|false $row */
      if (false === $row || $row['equipment_id'] !== (string) $attachment->equipmentId()) {
        throw AttachmentNotFoundException::withId((string) $attachment->id());
      }

      return EquipmentAttachment::reconstitute(
        AttachmentId::fromString((string) $row['id']),
        EquipmentId::fromString((string) $row['equipment_id']),
        new RestoredEquipmentAttachmentFile((string) $row['file_name'], (string) $row['storage_path'], (string) $row['mime_type'], (int) $row['size'], $row['label']),
        new DateTimeImmutable((string) $row['uploaded_at']),
      );
    });
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
