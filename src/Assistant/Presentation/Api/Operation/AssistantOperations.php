<?php

declare(strict_types=1);

namespace Assistant\Presentation\Api\Operation;

/**
 * Operation AssistantOperations.
 *
 * @category Operation
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final class AssistantOperations
{
  public const string LIST_ASSISTANT_THREADS = 'listAssistantThreads';

  public const string START_ASSISTANT_THREAD = 'startAssistantThread';

  public const string GET_ASSISTANT_THREAD = 'getAssistantThread';

  public const string ASK_ASSISTANT_QUESTION = 'askAssistantQuestion';

  public const string GET_ASSISTANT_THREAD_SUBSCRIPTION = 'getAssistantThreadSubscription';
}
