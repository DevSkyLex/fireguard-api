<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListDirectConversations;

use Shared\Application\Message\QueryMessage;

/**
 * UseCase ListDirectConversationsQuery.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListDirectConversationsQuery implements QueryMessage
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $userId the acting user id value
   * @param string $organizationId the organization id value
   * @param ?bool $isArchived optional archived-flag filter
   * @param int $page the page value
   * @param int $itemsPerPage the items per page value
   */
  public function __construct(
    public string $userId,
    public string $organizationId,
    public ?bool $isArchived = null,
    public int $page = 1,
    public int $itemsPerPage = 30,
  ) {
  }
}
