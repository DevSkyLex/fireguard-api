<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListConversations;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListConversationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationsQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param ?string $subjectType optional subject type filter
   * @param ?string $subjectId optional subject identifier filter
   * @param ?bool $isArchived optional archived-flag filter
   * @param bool $unreadOnly whether to only return conversations with unread activity
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?string $subjectType = null,
    public ?string $subjectId = null,
    public ?bool $isArchived = null,
    public bool $unreadOnly = false,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
