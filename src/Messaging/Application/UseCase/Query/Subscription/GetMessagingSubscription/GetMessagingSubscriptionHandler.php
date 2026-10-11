<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Subscription\GetMessagingSubscription;

use Messaging\Application\Port\Outbound\MessagingSubscriptionTokenPort;
use Messaging\Application\UseCase\Query\Conversation\GetConversation\{GetConversationQuery, GetConversationResult};
use Shared\Application\Message\QueryHandler;
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class GetMessagingSubscriptionHandler
 *
 * Reuses the canonical conversation read gate before issuing any subscription capability.
 *
 * @category UseCase
 */
final readonly class GetMessagingSubscriptionHandler implements QueryHandler
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Keeps token generation behind a pure outbound capability.
   *
   * @access public
   *
   * @param QueryBusPort $queries canonical read use cases
   * @param MessagingSubscriptionTokenPort $tokens scoped subscription token issuer
   *
   * @return void
   */
  public function __construct(private QueryBusPort $queries, private MessagingSubscriptionTokenPort $tokens)
  {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke
   *
   * Requires the identical subject/participant access policy as reading the conversation.
   *
   * @access public
   *
   * @param GetMessagingSubscriptionQuery $query subscription request
   *
   * @return GetMessagingSubscriptionResult authorized scoped subscription
   */
  public function __invoke(GetMessagingSubscriptionQuery $query): GetMessagingSubscriptionResult
  {
    /** @var GetConversationResult $conversation */
    $conversation = $this->queries->ask(new GetConversationQuery($query->userId, $query->conversationId));

    return new GetMessagingSubscriptionResult($this->tokens->create($conversation->conversation->organizationId, $query->conversationId));
  }
  // #endregion
}
