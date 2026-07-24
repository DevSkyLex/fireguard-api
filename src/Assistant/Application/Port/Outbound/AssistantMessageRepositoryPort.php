<?php

declare(strict_types=1);

namespace Assistant\Application\Port\Outbound;

use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\AssistantMessageId;

/**
 * Port AssistantMessageRepositoryPort.
 *
 * @category Port
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
interface AssistantMessageRepositoryPort
{
  // #region Methods
  /**
   * Method save.
   *
   * Persists an assistant message aggregate (insert on first save, update
   * thereafter — e.g. the `pending`/`streaming` -> `complete`|`failed`
   * transitions REPLACE the same row).
   *
   * @since 1.0.0
   *
   * @param AssistantMessage $message the assistant message aggregate
   */
  public function save(AssistantMessage $message): void;

  /**
   * Method findById.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageId $id the assistant message identifier
   *
   * @return ?AssistantMessage the assistant message when found
   */
  public function findById(AssistantMessageId $id): ?AssistantMessage;

  /**
   * Method listByThread.
   *
   * Lists a thread's messages in chronological order (oldest first).
   *
   * @since 1.0.0
   *
   * @param string $threadId the owning thread identifier
   * @param int $limit maximum number of results
   * @param int $offset result offset
   *
   * @return list<AssistantMessage> the matching assistant messages
   */
  public function listByThread(string $threadId, int $limit, int $offset): array;

  /**
   * Method countByThread.
   *
   * @since 1.0.0
   *
   * @param string $threadId the owning thread identifier
   *
   * @return int the matching assistant message count
   */
  public function countByThread(string $threadId): int;
  // #endregion
}
