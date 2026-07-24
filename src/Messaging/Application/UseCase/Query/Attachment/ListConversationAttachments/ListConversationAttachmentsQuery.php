<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Attachment\ListConversationAttachments;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListConversationAttachmentsQuery.
 *
 * Backs the conversation Files tab (`GET /conversations/{id}/attachments`).
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationAttachmentsQuery implements QueryMessage
{
  // #region Constructor
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
  // #endregion
}
