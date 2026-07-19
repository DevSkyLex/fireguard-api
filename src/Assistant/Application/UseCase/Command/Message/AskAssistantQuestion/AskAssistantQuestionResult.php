<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase AskAssistantQuestionResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class AskAssistantQuestionResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param string $threadId the assistant thread identifier
   * @param string $organizationId the owning organization identifier
   * @param AssistantMessageView $userMessage the persisted user message view
   * @param AssistantMessageView $assistantMessage the persisted pending assistant reply view
   */
  public function __construct(
    public string $threadId,
    public string $organizationId,
    public AssistantMessageView $userMessage,
    public AssistantMessageView $assistantMessage,
  ) {
  }
  // #endregion
}
