<?php

declare(strict_types=1);

namespace Intervention\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Intervention\Application\Port\Outbound\InterventionAttachmentRepositoryPort;
use Intervention\Domain\Model\Attachment\InterventionAttachment;
use Intervention\Domain\ValueObject\InterventionAttachmentId;
use Intervention\Infrastructure\Persistence\Doctrine\Mapper\InterventionAttachmentMapper;
use Intervention\Infrastructure\Persistence\Doctrine\Record\{InterventionAttachmentRecord, InterventionRecord};

use function array_map;

/**
 * Repository InterventionAttachmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class InterventionAttachmentRepository implements InterventionAttachmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<InterventionAttachmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(InterventionAttachmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(InterventionAttachment $attachment): void
  {
    $record = InterventionAttachmentMapper::toRecord($attachment);
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $attachment->interventionId());
    $record->intervention = $intervention;
    $existing = $this->repository->find($record->id);

    if ($existing instanceof InterventionAttachmentRecord) {
      $existing->intervention = $intervention;
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
  public function findById(InterventionAttachmentId $id): ?InterventionAttachment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InterventionAttachmentRecord) {
      return null;
    }

    return InterventionAttachmentMapper::toDomain($record);
  }

  /**
   * Method findByInterventionId.
   *
   * @since 1.0.0
   */
  public function findByInterventionId(string $interventionId): array
  {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);
    $records = $this->repository->findBy(
      ['intervention' => $intervention],
      ['uploadedAt' => 'DESC'],
    );

    return array_map(
      static fn (InterventionAttachmentRecord $record): InterventionAttachment => InterventionAttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method countByInterventionId.
   *
   * @since 1.0.0
   */
  public function countByInterventionId(string $interventionId): int
  {
    /** @var InterventionRecord $intervention */
    $intervention = $this->entityManager->getReference(InterventionRecord::class, $interventionId);

    return $this->repository->count(['intervention' => $intervention]);
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(InterventionAttachmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof InterventionAttachmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
