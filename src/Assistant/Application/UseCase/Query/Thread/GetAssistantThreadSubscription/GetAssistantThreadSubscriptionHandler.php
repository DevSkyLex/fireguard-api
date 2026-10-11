<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThreadSubscription;

use Assistant\Application\Contract\Realtime\AssistantThreadTopic;
use Assistant\Application\Port\Outbound\Internal\AssistantSubscriberTokenPort;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadQuery, GetAssistantThreadResult};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class GetAssistantThreadSubscriptionHandler
 *
 * Completes the canonical scoped read before minting a member-private subscription.
 *
 * @category Handler
 */
final readonly class GetAssistantThreadSubscriptionHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * @access public
   *
   * @param QueryBusPort $queries canonical owner-local thread authorization and projection
   * @param AssistantSubscriberTokenPort $tokens scoped subscriber credentials
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queries, private AssistantSubscriberTokenPort $tokens)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * @access public
   *
   * @param GetAssistantThreadSubscriptionQuery $query actor and thread scope
   *
   * @return GetAssistantThreadSubscriptionResult subscriber credential for the authorized thread
   */
  public function __invoke(GetAssistantThreadSubscriptionQuery $query): GetAssistantThreadSubscriptionResult
  {
    /** @var GetAssistantThreadResult $thread */
    $thread = $this->queries->ask(new GetAssistantThreadQuery(
      organizationId: $query->organizationId,
      threadId: $query->threadId,
      actorUserId: $query->actorUserId,
      messagesPage: 1,
      messagesItemsPerPage: 1,
    ));
    $topic = AssistantThreadTopic::forThread($thread->thread->organizationId, $thread->thread->id);

    return new GetAssistantThreadSubscriptionResult($this->tokens->create($topic), $topic);
  }
  // #endregion
}
