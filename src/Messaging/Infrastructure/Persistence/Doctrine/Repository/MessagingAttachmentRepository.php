<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Messaging\Application\Contract\Attachment\MessagingAttachmentPage;
use Messaging\Application\Port\Outbound\MessagingAttachmentRepositoryPort;
use Messaging\Domain\Model\Attachment\MessagingAttachment;
use Messaging\Domain\ValueObject\MessagingAttachmentId;
use Messaging\Infrastructure\Persistence\Doctrine\Mapper\MessagingAttachmentMapper;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingAttachmentRecord, MessagingMessageRecord};

use function array_map;
use function max;
use function min;

/**
 * Repository MessagingAttachmentRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingAttachmentRepository implements MessagingAttachmentRepositoryPort
{
  // #region Properties
  /**
   * @var EntityRepository<MessagingAttachmentRecord>
   */
  private EntityRepository $repository;
  // #endregion

  // #region Constructor
  public function __construct(
    private EntityManagerInterface $entityManager,
  ) {
    $this->repository = $this->entityManager->getRepository(MessagingAttachmentRecord::class);
  }
  // #endregion

  // #region Methods
  /**
   * Method save.
   *
   * @since 1.0.0
   */
  public function save(MessagingAttachment $attachment): void
  {
    $record = MessagingAttachmentMapper::toRecord($attachment);
    /** @var MessagingMessageRecord $message */
    $message = $this->entityManager->getReference(MessagingMessageRecord::class, $attachment->messageId());
    $record->message = $message;

    $existing = $this->repository->find($record->id);

    if ($existing instanceof MessagingAttachmentRecord) {
      $existing->message = $message;
      $existing->conversationId = $record->conversationId;
      $existing->organizationId = $record->organizationId;
      $existing->uploadedByMemberId = $record->uploadedByMemberId;
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
  public function findById(MessagingAttachmentId $id): ?MessagingAttachment
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof MessagingAttachmentRecord) {
      return null;
    }

    return MessagingAttachmentMapper::toDomain($record);
  }

  /**
   * Method findByMessageIds.
   *
   * @since 1.0.0
   */
  public function findByMessageIds(array $messageIds): array
  {
    if ([] === $messageIds) {
      return [];
    }

    /** @var list<MessagingAttachmentRecord> $records */
    $records = $this->entityManager->createQueryBuilder()
      ->select('a')
      ->from(MessagingAttachmentRecord::class, 'a')
      ->where('IDENTITY(a.message) IN (:messageIds)')
      ->setParameter('messageIds', $messageIds)
      ->orderBy('a.uploadedAt', 'ASC')
      ->getQuery()
      ->getResult();

    return array_map(
      static fn (MessagingAttachmentRecord $record): MessagingAttachment => MessagingAttachmentMapper::toDomain($record),
      $records,
    );
  }

  /**
   * Method listByConversationId.
   *
   * @since 1.0.0
   */
  public function listByConversationId(string $conversationId, int $page, int $itemsPerPage): MessagingAttachmentPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));

    $qb = $this->entityManager->createQueryBuilder()
      ->select('a')
      ->from(MessagingAttachmentRecord::class, 'a')
      ->where('a.conversationId = :conversationId')
      ->setParameter('conversationId', $conversationId);

    $total = (int) (clone $qb)
      ->select('COUNT(a.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingAttachmentRecord> $records */
    $records = $qb
      ->orderBy('a.uploadedAt', 'DESC')
      // Unique tiebreaker: without it rows tied on the sort above are ordered
      // arbitrarily, and LIMIT/OFFSET then repeats some across pages.
      ->addOrderBy('a.id', 'ASC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagingAttachmentPage(
      array_map(
        static fn (MessagingAttachmentRecord $record): MessagingAttachment => MessagingAttachmentMapper::toDomain($record),
        $records,
      ),
      $page,
      $itemsPerPage,
      $total,
    );
  }

  /**
   * Method delete.
   *
   * @since 1.0.0
   */
  public function delete(MessagingAttachmentId $id): void
  {
    $record = $this->repository->find((string) $id);

    if (!$record instanceof MessagingAttachmentRecord) {
      return;
    }

    $this->entityManager->remove($record);
    $this->entityManager->flush();
  }
  // #endregion
}
