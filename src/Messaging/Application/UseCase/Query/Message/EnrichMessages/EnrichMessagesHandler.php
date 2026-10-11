<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\EnrichMessages;

use Messaging\Application\Contract\Message\MessageView;
use Messaging\Application\Port\Outbound\Internal\MessagingAttachmentRepositoryPort;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Shared\Application\Message\QueryHandler;

use function array_map;
use function array_merge;
use function array_unique;
use function array_values;

/**
 * Class EnrichMessagesHandler
 *
 * Owns the four batched reads formerly performed by the HTTP output factory.
 * Caller use cases have already checked conversation access and resolved the active member.
 *
 * @category UseCase
 */
final readonly class EnrichMessagesHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Injects owner-published persistence and member projection capabilities.
   *
   * @access public
   *
   * @param MessagingAttachmentRepositoryPort $attachments attachment projection source
   * @param MessagingReactionRepositoryPort $reactions reaction projection source
   * @param MessagingSavedMessageRepositoryPort $savedMessages current-member saved state
   * @param MessagingMemberDirectoryPort $members member names source
   *
   * @return void
   */
  public function __construct(private MessagingAttachmentRepositoryPort $attachments, private MessagingReactionRepositoryPort $reactions, private MessagingSavedMessageRepositoryPort $savedMessages, private MessagingMemberDirectoryPort $members)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Loads whole-page attachments, reactions, saved state and names with a bounded query count.
   *
   * @access public
   *
   * @param EnrichMessagesQuery $query authorized views and reading member
   *
   * @return EnrichMessagesResult grouped projections
   */
  public function __invoke(EnrichMessagesQuery $query): EnrichMessagesResult
  {
    $ids = array_map(static fn (MessageView $view): string => $view->id, $query->views);
    $attachments = [];
    foreach ($this->attachments->findByMessageIds($ids) as $attachment) {
      $attachments[$attachment->messageId][] = $attachment;
    }
    $reactions = [];
    foreach ($this->reactions->findByMessageIds($ids) as $reaction) {
      $reactions[$reaction->messageId][] = $reaction;
    }
    $saved = $this->savedMessages->findSavedMessageIds($query->currentMemberId, $ids);
    $memberIds = [];
    foreach ($query->views as $view) {
      $memberIds[] = [$view->authorMemberId];
      $memberIds[] = $view->mentions;
      if (null !== $view->pinnedByMemberId) {
        $memberIds[] = [$view->pinnedByMemberId];
      }
    }
    $names = [] === $query->views ? [] : $this->members->displayNamesFor($query->views[0]->organizationId, array_values(array_unique(array_merge(...$memberIds))));

    return new EnrichMessagesResult($attachments, $reactions, $saved, $names);
  }
  // #endregion
}
