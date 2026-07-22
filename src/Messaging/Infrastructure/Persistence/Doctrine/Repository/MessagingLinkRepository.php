<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Persistence\Doctrine\Repository;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Messaging\Application\Contract\Link\{MessagingLinkPage, MessagingLinkView};
use Messaging\Application\Port\Outbound\MessagingLinkRepositoryPort;
use Messaging\Infrastructure\Persistence\Doctrine\Record\{MessagingMessageLinkRecord, MessagingMessageRecord};
use Shared\Application\Factory\UuidFactory;

use function array_map;
use function max;
use function min;

/**
 * Repository MessagingLinkRepository.
 *
 * @category Repository
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class MessagingLinkRepository implements MessagingLinkRepositoryPort
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param EntityManagerInterface $entityManager the entity manager value
   * @param UuidFactory $uuidFactory the uuid factory value
   */
  public function __construct(
    private EntityManagerInterface $entityManager,
    private UuidFactory $uuidFactory,
  ) {
  }
  // #endregion

  // #region Methods
  public function replaceForMessage(string $messageId, string $conversationId, array $urls, DateTimeImmutable $extractedAt): void
  {
    // A plain DELETE scoped to the message, never a load-then-remove cycle —
    // mirrors `MessagingSavedMessageRepository::unsave()`'s raw-DBAL style.
    // Runs on BOTH create (no existing rows — a no-op) and edit (replaces
    // the prior extraction), so this method alone is the ONE write path a
    // caller needs, never branching on create vs. edit.
    $this->entityManager->getConnection()->executeStatement(
      'DELETE FROM messaging_message_link WHERE message_id = :messageId',
      ['messageId' => $messageId],
    );

    if ([] === $urls) {
      return;
    }

    /** @var MessagingMessageRecord $message */
    $message = $this->entityManager->getReference(MessagingMessageRecord::class, $messageId);

    foreach ($urls as $url) {
      $record = new MessagingMessageLinkRecord();
      $record->id = $this->uuidFactory->generateRaw();
      $record->message = $message;
      $record->conversationId = $conversationId;
      $record->url = $url;
      $record->createdAt = $extractedAt;

      $this->entityManager->persist($record);
    }

    $this->entityManager->flush();
  }

  public function listByConversation(string $conversationId, int $page, int $itemsPerPage): MessagingLinkPage
  {
    $page = max(1, $page);
    $itemsPerPage = max(1, min(100, $itemsPerPage));

    $qb = $this->entityManager->createQueryBuilder()
      ->select('l')
      ->from(MessagingMessageLinkRecord::class, 'l')
      ->where('l.conversationId = :conversationId')
      ->setParameter('conversationId', $conversationId);

    $total = (int) (clone $qb)
      ->select('COUNT(l.id)')
      ->getQuery()
      ->getSingleScalarResult();

    /** @var list<MessagingMessageLinkRecord> $records */
    $records = $qb
      ->orderBy('l.createdAt', 'DESC')
      ->setFirstResult(($page - 1) * $itemsPerPage)
      ->setMaxResults($itemsPerPage)
      ->getQuery()
      ->getResult();

    return new MessagingLinkPage(
      array_map($this->view(...), $records),
      $page,
      $itemsPerPage,
      $total,
    );
  }

  /**
   * Method view.
   *
   * @since 1.0.0
   *
   * @param MessagingMessageLinkRecord $record the record value
   *
   * @return MessagingLinkView the link view result
   */
  private function view(MessagingMessageLinkRecord $record): MessagingLinkView
  {
    return new MessagingLinkView(
      $record->id,
      $record->message instanceof MessagingMessageRecord ? $record->message->id : '',
      $record->conversationId,
      $record->url,
      $record->label,
      $record->createdAt,
    );
  }
  // #endregion
}
