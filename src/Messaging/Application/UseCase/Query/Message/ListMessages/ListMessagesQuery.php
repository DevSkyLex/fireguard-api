<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListMessages;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListMessagesQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListMessagesQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $conversationId the conversation id value
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $conversationId,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
