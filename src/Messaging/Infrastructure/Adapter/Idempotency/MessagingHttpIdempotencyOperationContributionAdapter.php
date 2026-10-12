<?php

declare(strict_types=1);

namespace Messaging\Infrastructure\Adapter\Idempotency;

use Shared\Application\Contract\Http\{HttpIdempotencyOperation, HttpIdempotencyQueryProjection};
use Shared\Application\Port\Outbound\Idempotency\HttpIdempotencyOperationContributionPort;

/**
 * Class MessagingHttpIdempotencyOperationContributionAdapter
 *
 * Declares exact owner mutations, transaction ownership and bounded replay result schemas.
 *
 * @category Adapter
 */
final readonly class MessagingHttpIdempotencyOperationContributionAdapter implements HttpIdempotencyOperationContributionPort
{
  // #region Methods
  /**
   * Method definitions
   *
   * @access public
   *
   * @return list<HttpIdempotencyOperation> registered owner mutations requiring client keys
   */
  public static function definitions(): array
  {
    return [
      ...self::channelDefinitions(),
      ...self::conversationDefinitions(),
      ...self::messageDefinitions(),
      ...self::messageInteractionDefinitions(),
      ...self::attachmentAndPresenceDefinitions(),
    ];
  }

  /**
   * Method channelDefinitions
   *
   * Preserves the declared owner operations and their original replay metadata.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> ordered owner operation definitions
   */
  private static function channelDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: '_api_/channels_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\CreateChannel\CreateChannelCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\CreateChannel\CreateChannelResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ChannelView::class],
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'channel.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/channels/{id}_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\UpdateChannel\UpdateChannelCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\UpdateChannel\UpdateChannelResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ChannelView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'channel.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/channels/{id}_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\UpdateChannel\UpdateChannelCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\UpdateChannel\UpdateChannelResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ChannelView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'channel.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_channel_add_participant',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\AddChannelParticipant\AddChannelParticipantCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\AddChannelParticipant\AddChannelParticipantResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ParticipantView::class],
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_channel_remove_participant',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant\RemoveChannelParticipantCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\RemoveChannelParticipant\RemoveChannelParticipantResult::class,
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversationId',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_channel_bind_team',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\BindChannelTeam\BindChannelTeamCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\BindChannelTeam\BindChannelTeamResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ChannelView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'channel.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_channel_set_parent',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Channel\SetChannelParent\SetChannelParentCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Channel\SetChannelParent\SetChannelParentResult::class,
        contractClasses: [\Messaging\Application\Contract\Channel\ChannelView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'channel.id',
        resourceKind: 'messaging_conversation',
      ),
    ];
  }

  /**
   * Method conversationDefinitions
   *
   * Preserves the declared owner operations and their original replay metadata.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> ordered owner operation definitions
   */
  private static function conversationDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: '_api_/conversations_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation\GetOrCreateConversationCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\GetOrCreateConversation\GetOrCreateConversationResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/conversations/{id}_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\ArchiveConversation\ArchiveConversationCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\ArchiveConversation\ArchiveConversationResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_mark_conversation_read',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead\MarkConversationReadCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\ReadMarker\MarkConversationRead\MarkConversationReadResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_conversation_delivery',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery\AcknowledgeDeliveryCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\ReadMarker\AcknowledgeDelivery\AcknowledgeDeliveryResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_conversation_typing',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\PublishTyping\PublishTypingCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\PublishTyping\PublishTypingResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'favorite_conversation',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\FavoriteConversation\FavoriteConversationCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\FavoriteConversation\FavoriteConversationResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: 'unfavorite_conversation',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation\UnfavoriteConversationCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\UnfavoriteConversation\UnfavoriteConversationResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
      new HttpIdempotencyOperation(
        name: '_api_/direct-conversations_post',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\GetOrCreateDirectConversationCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Conversation\GetOrCreateDirectConversation\GetOrCreateDirectConversationResult::class,
        contractClasses: [\Messaging\Application\Contract\Conversation\ConversationView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'conversation.id',
        resourceKind: 'messaging_conversation',
      ),
    ];
  }

  /**
   * Method messageDefinitions
   *
   * Preserves the declared owner operations and their original replay metadata.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> ordered owner operation definitions
   */
  private static function messageDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: '_api_/conversations/{conversationId}/messages_post',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\PostMessage\PostMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\PostMessage\PostMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: 'put_message',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\PostMessage\PostMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\PostMessage\PostMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/messages/{id}_patch',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\EditMessage\EditMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\EditMessage\EditMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: '_api_/messages/{id}_delete',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\DeleteMessage\DeleteMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\DeleteMessage\DeleteMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
      ),
      new HttpIdempotencyOperation(
        name: 'pin_message',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\PinMessage\PinMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\PinMessage\PinMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
    ];
  }

  /**
   * Method messageInteractionDefinitions
   *
   * Preserves the declared owner operations and their original replay metadata.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> ordered owner operation definitions
   */
  private static function messageInteractionDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'unpin_message',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\UnpinMessage\UnpinMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\UnpinMessage\UnpinMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
      ),
      new HttpIdempotencyOperation(
        name: 'add_reaction',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\AddReaction\AddReactionCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\AddReaction\AddReactionResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: 'remove_reaction',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\RemoveReaction\RemoveReactionCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\RemoveReaction\RemoveReactionResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
      ),
      new HttpIdempotencyOperation(
        name: 'post_reply',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\PostReply\PostReplyCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\PostReply\PostReplyResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: 'save_message',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\SaveMessage\SaveMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\SaveMessage\SaveMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 200,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
        queryProjections: [new HttpIdempotencyQueryProjection(
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesQuery::class,
          \Messaging\Application\UseCase\Query\Message\EnrichMessages\EnrichMessagesResult::class,
          [\Messaging\Application\Contract\Message\MessageView::class, \Messaging\Application\Contract\Attachment\MessagingAttachmentView::class, \Messaging\Application\Contract\Reaction\MessageReactionView::class],
          captureBeforePrimary: false,
        )],
      ),
      new HttpIdempotencyOperation(
        name: 'unsave_message',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Message\UnsaveMessage\UnsaveMessageCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Message\UnsaveMessage\UnsaveMessageResult::class,
        contractClasses: [\Messaging\Application\Contract\Message\MessageView::class],
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'message.id',
        resourceKind: 'messaging_message',
      ),
    ];
  }

  /**
   * Method attachmentAndPresenceDefinitions
   *
   * Preserves the declared owner operations and their original replay metadata.
   *
   * @access private
   *
   * @return list<HttpIdempotencyOperation> ordered owner operation definitions
   */
  private static function attachmentAndPresenceDefinitions(): array
  {
    return [
      new HttpIdempotencyOperation(
        name: 'add_message_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\AddMessageAttachmentCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Attachment\AddMessageAttachment\AddMessageAttachmentResult::class,
        statusCode: 201,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'messaging_attachment',
      ),
      new HttpIdempotencyOperation(
        name: 'delete_message_attachment',
        database: 'main',
        transactionMode: 'root_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Attachment\DeleteMessageAttachment\DeleteMessageAttachmentResult::class,
        statusCode: 204,
        secret: false,
        requiredKey: true,
        resultIdentifierProperty: 'attachmentId',
        resourceKind: 'messaging_attachment',
      ),
      new HttpIdempotencyOperation(
        name: 'messaging_ping_presence',
        database: 'main',
        transactionMode: 'bus_owner',
        primaryCommand: \Messaging\Application\UseCase\Command\Presence\PingPresence\PingPresenceCommand::class,
        resultClass: \Messaging\Application\UseCase\Command\Presence\PingPresence\PingPresenceResult::class,
        statusCode: 200,
        secret: false,
        requiredKey: true,
      ),
    ];
  }
  // #endregion
}
