<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Conversation\ListConversations;

use Messaging\Application\Port\Outbound\{MessagingConversationFavoriteRepositoryPort, MessagingConversationRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\MessagingSubjectType;
use Shared\Application\Message\QueryHandler;
use ValueError;

use function array_map;
use function sprintf;

/**
 * UseCase ListConversationsHandler.
 *
 * Gates by `organization.messaging.read` alone (no per-subject filtering,
 * for cost): an archived or subject-restricted thread is listed here, but
 * opening it (`GetConversation`/`ListMessages`/subscription) additionally
 * requires the subject's own read permission.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class ListConversationsHandler implements QueryHandler
{
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param MessagingConversationRepositoryPort $conversations the conversation repository port
   * @param MessagingReadMarkerRepositoryPort $readMarkers the read marker repository port
   * @param MessagingConversationFavoriteRepositoryPort $favorites the conversation favorite repository port
   * @param MessagingAccessPolicy $accessPolicy the messaging access policy
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingConversationFavoriteRepositoryPort $favorites,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param ListConversationsQuery $query the query value
   *
   * @return ListConversationsResult the use case result
   */
  public function __invoke(ListConversationsQuery $query): ListConversationsResult
  {
    $this->accessPolicy->assertCanListConversations($query->userId, $query->organizationId);

    $subjectType = null;
    if (null !== $query->subjectType) {
      try {
        $subjectType = MessagingSubjectType::from($query->subjectType);
      } catch (ValueError) {
        throw new MessagingValidationException(sprintf('Unsupported subject type "%s".', $query->subjectType));
      }
    }

    $memberId = $this->accessPolicy->resolveActiveMemberId($query->organizationId, $query->userId);

    $page = $this->conversations->list(
      $query->organizationId,
      $subjectType,
      $query->subjectId,
      $query->isArchived,
      $query->unreadOnly ? $memberId : null,
      $query->page,
      $query->itemsPerPage,
    );

    $ids = array_map(static fn ($view): string => $view->id, $page->items);
    $unreadCounts = [] === $ids ? [] : $this->readMarkers->unreadCounts($query->organizationId, $memberId, $ids);
    $favoriteConversationIds = [] === $ids ? [] : $this->favorites->findFavoritedConversationIds($memberId, $ids);

    return new ListConversationsResult($page, $unreadCounts, $favoriteConversationIds);
  }
}
