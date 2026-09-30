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
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies conversation membership, receipt reads and the access policy for receipt queries.
   *
   * @access public
   *
   * @param MessagingConversationRepositoryPort $conversations loads the conversation
   * @param MessagingParticipantRepositoryPort $participants lists conversation members
   * @param MessagingReadMarkerRepositoryPort $readMarkers reads participant receipt positions
   * @param MessagingAccessPolicy $accessPolicy checks conversation membership and visibility
   *
   * @return void
   */
  public function __construct(
    private MessagingConversationRepositoryPort $conversations,
    private MessagingParticipantRepositoryPort $participants,
    private MessagingReadMarkerRepositoryPort $readMarkers,
    private MessagingAccessPolicy $accessPolicy,
  ) {
  }

  // #endregion
  // #region Methods
  /**
   * Method __invoke
   *
   * Returns delivery receipt positions after verifying access to a participant conversation.
   *
   * @access public
   *
   * @param GetConversationReceiptsQuery $query the user and conversation context
   *
   * @return GetConversationReceiptsResult the receipt position data
   *
   * @throws MessagingNotFoundException when the conversation is unavailable
   * @throws MessagingValidationException when receipts are not supported for the conversation
   */
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
  // #endregion
}
