<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Thread\StartAssistantThread;

use Assistant\Application\Contract\Thread\AssistantThreadView;
use Shared\Application\Message\ResultMessage;

/**
 * UseCase StartAssistantThreadResult.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class StartAssistantThreadResult implements ResultMessage
{
  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantThreadView $thread the started assistant thread view
   */
  public function __construct(
    public AssistantThreadView $thread,
  ) {
  }
  // #endregion
}
