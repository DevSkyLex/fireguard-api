<?php

declare(strict_types=1);

namespace Messaging\Application\UseCase\Query\Message\EnrichMessages;

use Messaging\Application\Contract\Attachment\MessagingAttachmentView;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use Shared\Application\Message\ResultMessage;

/**
 * Class EnrichMessagesResult
 *
 * Contains only immutable projections needed by the deterministic HTTP mapper.
 *
 * @category UseCase
 */
final readonly class EnrichMessagesResult implements ResultMessage
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Groups bounded reads for a complete message page.
   *
   * @access public
   *
   * @param array<string, list<MessagingAttachmentView>> $attachments attachments indexed by message
   * @param array<string, list<MessageReactionView>> $reactions reactions indexed by message
   * @param list<string> $savedMessageIds identifiers saved by the current member
   * @param array<string, string> $displayNames member names indexed by member identifier
   *
   * @return void
   */
  public function __construct(public array $attachments, public array $reactions, public array $savedMessageIds, public array $displayNames)
  {
  }
  // #endregion
}
