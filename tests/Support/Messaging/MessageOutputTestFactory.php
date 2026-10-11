<?php

declare(strict_types=1);

namespace Tests\Support\Messaging;

use InvalidArgumentException;
use Messaging\Application\Port\Outbound\Internal\MessagingAttachmentRepositoryPort;
use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingReactionRepositoryPort, MessagingSavedMessageRepositoryPort};
use Messaging\Application\UseCase\Query\Message\EnrichMessages\{EnrichMessagesHandler, EnrichMessagesQuery};
use Messaging\Presentation\Api\Factory\{MessageAttachmentOutputFactory, MessageOutputFactory};
use Messaging\Presentation\Api\Service\MessageOutputPresenter;
use Shared\Application\Message\{QueryMessage, ResultMessage};
use Shared\Application\Port\Inbound\QueryBusPort;

/**
 * Class MessageOutputTestFactory
 *
 * Builds the real projection use case and deterministic mapper around supplied test ports.
 *
 * @category Test Support
 */
final class MessageOutputTestFactory
{
  // #region Methods
  /**
   * Method create
   *
   * Keeps existing HTTP contract cases while testing the relocated batched read behavior.
   *
   * @access public
   *
   * @param MessagingAttachmentRepositoryPort $attachments attachment test port
   * @param MessageAttachmentOutputFactory $attachmentMapper attachment mapper
   * @param MessagingReactionRepositoryPort $reactions reaction test port
   * @param MessagingSavedMessageRepositoryPort $saved saved-message test port
   * @param MessagingMemberDirectoryPort $members member-directory test port
   *
   * @return MessageOutputPresenter HTTP projection translator
   */
  public static function create(MessagingAttachmentRepositoryPort $attachments, MessageAttachmentOutputFactory $attachmentMapper, MessagingReactionRepositoryPort $reactions, MessagingSavedMessageRepositoryPort $saved, MessagingMemberDirectoryPort $members): MessageOutputPresenter
  {
    $handler = new EnrichMessagesHandler($attachments, $reactions, $saved, $members);
    $bus = new class ($handler) implements QueryBusPort {
      public function __construct(private readonly EnrichMessagesHandler $handler)
      {
      }

      public function ask(QueryMessage $query): ResultMessage
      {
        if (!$query instanceof EnrichMessagesQuery) {
          throw new InvalidArgumentException('Unexpected query in message projection test.');
        }

        return ($this->handler)($query);
      }
    };

    return new MessageOutputPresenter($bus, new MessageOutputFactory($attachmentMapper));
  }
  // #endregion
}
