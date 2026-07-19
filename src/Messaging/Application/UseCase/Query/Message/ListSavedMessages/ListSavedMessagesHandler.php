<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\ListSavedMessages;

use Messaging\Application\Port\Outbound\MessagingMessageRepositoryPort;
use Messaging\Application\Service\MessagingAccessPolicy;
use Shared\Application\Message\QueryHandler;

/**
 * UseCase ListSavedMessagesHandler.
 *
 * Gated by active organization membership ALONE
 * (`MessagingAccessPolicy::resolveActiveMemberId()`) — no per-conversation
 * or per-subject re-check, mirroring `ListConversationsHandler`'s
 * documented "list is cheaper than open" stance. This is safe because the
 * query is intrinsically scoped to the CALLER's own saved rows
 * (`(member_id, message_id)`): a save is private by construction, so there
 * is no cross-member data exposure to guard against here, unlike listing
 * conversations/messages by subject. A member's saved list MAY therefore
 * include a message whose subject they have since lost read access to —
 * this is an accepted, deliberate trade-off (their own personal bookmark of
 * something they could once read, analogous to a private note), never a
 * leak to a DIFFERENT member.
 *
 * @category UseCase
 *
 * @version 1.2.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListSavedMessagesHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.2.0
   *
   * @param MessagingMessageRepositoryPort $messages the message repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingMessageRepositoryPort $messages,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.2.0
   *
   * @param ListSavedMessagesQuery $query the query value
   *
   * @return ListSavedMessagesResult the use case result
   */
  public function __invoke(ListSavedMessagesQuery $query): ListSavedMessagesResult
  {
    $memberId = $this->accessPolicy->resolveActiveMemberId($query->organizationId, $query->userId);

    return new ListSavedMessagesResult(
      $this->messages->listSavedByMember($query->organizationId, $memberId, $query->page, $query->itemsPerPage),
      $memberId,
    );
  }
}
