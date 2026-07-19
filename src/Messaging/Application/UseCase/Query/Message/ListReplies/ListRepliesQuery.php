<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListReplies;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListRepliesQuery.
 *
 * Backs a message's Thread panel (`GET /messages/{id}/replies`, L2.5).
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListRepliesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param string $userId the acting user id value
   * @param string $parentMessageId the parent (root) message id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $parentMessageId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
