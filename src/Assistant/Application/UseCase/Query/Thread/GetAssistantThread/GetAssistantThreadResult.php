<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Contract\Thread\AssistantThreadView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase GetAssistantThreadResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GetAssistantThreadResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadView $thread the assistant thread view
   * @param list<AssistantMessageView> $messages the page of the thread's messages, oldest first
   * @param int $messagesPage the current messages page number
   * @param int $messagesItemsPerPage the messages page size
   * @param int $messagesTotal the total matching message count
   */
  public function __construct(
    public AssistantThreadView $thread,
    public array $messages,
    public int $messagesPage,
    public int $messagesItemsPerPage,
    public int $messagesTotal,
  ) {
  }
  // #endregion
}
