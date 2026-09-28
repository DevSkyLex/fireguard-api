<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\ReadMarker\GetConversationReceipts;

use Messaging\Application\Port\Outbound\{MessagingConversationRepositoryPort, MessagingParticipantRepositoryPort, MessagingReadMarkerRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingNotFoundException, MessagingValidationException};
use Messaging\Domain\ValueObject\ConversationVisibility;
use Shared\Application\Message\QueryHandler;

/** Returns only receipt positions belonging to current conversation participants. */
final readonly class GetConversationReceiptsHandler implements QueryHandler
{
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  public function __invoke(GetConversationReceiptsQuery $query): GetConversationReceiptsResult
  {
    $conversation = $this->conversations->findById($query->conversationId);
    if (null === $conversation) {
      throw MessagingNotFoundException::conversation($query->conversationId);
    }
    $memberId = $this->accessPolicy->resolveActiveMemberId($conversation->organizationId, $query->userId);
    if (ConversationVisibility::PARTICIPANTS->value !== $conversation->visibility) {
      throw new MessagingValidationException('Receipts require a participant conversation.');
    }
    $this->accessPolicy->assertCanReadChannel($query->userId, $conversation->organizationId, $conversation->id, $memberId);

    return new GetConversationReceiptsResult($this->readMarkers->receiptPositions($conversation->id, $this->participants->listMemberIds($conversation->id)));
  }
}
