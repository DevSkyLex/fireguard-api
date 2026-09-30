<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Operation;

/**
 * Operation AssistantOperations.
 *
 * @category Operation
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantOperations
{
  /**
   * Constant LIST_ASSISTANT_THREADS
   */
  public const string LIST_ASSISTANT_THREADS = 'listAssistantThreads';

  /**
   * Constant START_ASSISTANT_THREAD
   */
  public const string START_ASSISTANT_THREAD = 'startAssistantThread';

  /**
   * Constant GET_ASSISTANT_THREAD
   */
  public const string GET_ASSISTANT_THREAD = 'getAssistantThread';

  /**
   * Constant ASK_ASSISTANT_QUESTION
   */
  public const string ASK_ASSISTANT_QUESTION = 'askAssistantQuestion';

  /**
   * Constant GET_ASSISTANT_THREAD_SUBSCRIPTION
   */
  public const string GET_ASSISTANT_THREAD_SUBSCRIPTION = 'getAssistantThreadSubscription';
}
