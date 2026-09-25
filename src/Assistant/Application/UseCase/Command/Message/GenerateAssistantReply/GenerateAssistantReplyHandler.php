<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\GenerateAssistantReply;

use Assistant\Application\Contract\Context\AssistantContextScope;
use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use Assistant\Application\Port\Outbound\{AssistantGenerationClientPort, AssistantMessageRepositoryPort, AssistantRealtimePublisherPort, AssistantThreadRepositoryPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\{AssistantAttemptWriter, AssistantPromptBuilder};
use Assistant\Domain\Exception\AssistantGenerationStoppedException;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageStatus, AssistantThreadId};
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, LoggerPort};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_filter;
use function array_slice;
use function array_values;
use function max;
use function min;

use const PHP_INT_MAX;

/**
 * UseCase GenerateAssistantReplyHandler.
 *
 * Consumes the `assistant` Messenger transport. Loads the pending assistant
 * reply, assembles the prompt (system prompt + completed transcript, via
 * {@see AssistantPromptBuilder}), and streams a chat completion through
 * {@see AssistantGenerationClientPort}, republishing every fragment to
 * Mercure via {@see AssistantRealtimePublisherPort} as it arrives.
 *
 * A pending attempt is claimed once under the message lock. Redelivery never
 * takes over a streaming attempt; cancellation and explicit retry fence every
 * subsequent fragment and completion. Each attempt has its own deadline.
 *
 * @category UseCase
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
final readonly class GenerateAssistantReplyHandler implements CommandHandler
{
  // #region Constants
  /**
   * Constant DEFAULT_TEMPERATURE.
   *
   * Used when neither the request nor the thread supplies a temperature.
   *
   * @since 1.0.0
   *
   * @var float DEFAULT_TEMPERATURE
   */
  private const float DEFAULT_TEMPERATURE = 0.7;
  // #endregion

  // #region Constructor
  /**
   * Constructor.
   *
   * @since 1.0.0
   *
   * @param AssistantMessageRepositoryPort $messages the assistant message repository port
   * @param AssistantThreadRepositoryPort $threads the assistant thread repository port
   * @param AssistantGenerationClientPort $client the Ollama generation client port
   * @param AssistantPromptBuilder $promptBuilder the prompt builder
   * @param ClockPort $clock the clock port
   * @param LoggerPort $logger the logger port
   * @param AssistantOrganizationSettingsPort $organizationSettings the organization assistant settings port (L2.2: `includeBusinessContext` gate)
   * @param string $defaultModel the operator's default model, used when neither the request nor the thread selects one
   * @param int $timeoutSeconds the outbound request timeout, in seconds
   */
  public function __construct(
    private AssistantMessageRepositoryPort $messages,
    private AssistantThreadRepositoryPort $threads,
    private AssistantGenerationClientPort $client,
    private AssistantPromptBuilder $promptBuilder,
    private ClockPort $clock,
    private LoggerPort $logger,
    private AssistantOrganizationSettingsPort $organizationSettings,
    private AssistantAttemptWriter $attempts,
    #[Autowire('%env(OLLAMA_DEFAULT_MODEL)%')]
    private string $defaultModel = '',
    #[Autowire('%env(int:OLLAMA_HTTP_TIMEOUT)%')]
    private int $timeoutSeconds = 120,
  ) {
  }
  // #endregion

  // #region Methods
  /**
   * Method __invoke.
   *
   * @since 1.0.0
   *
   * @param GenerateAssistantReplyCommand $command the command payload
   *
   * @return VoidResult the command result
   */
  public function __invoke(GenerateAssistantReplyCommand $command): VoidResult
  {
    $message = $this->attempts->claim($command);
    if (null !== $message) {
      $this->generateClaimedReply($command, $message);
    }

    return new VoidResult();
  }

  /**
   * Generates only after the pending attempt has been claimed.
   *
   * @param GenerateAssistantReplyCommand $command the command payload
   * @param AssistantMessage $message the claimed assistant reply
   */
  private function generateClaimedReply(GenerateAssistantReplyCommand $command, AssistantMessage $message): void
  {
    $attemptId = $message->attemptId() ?? (string) $message->id();

    $thread = $this->threads->findById(AssistantThreadId::fromString($command->threadId));

    if (null === $thread) {
      $this->attempts->finish((string) $message->id(), $attemptId, new AssistantGenerationOutcome('', null, 'assistant_thread_not_found'));

      return;
    }

    $model = $command->model ?? $thread->model() ?? $this->defaultModel;

    if ('' === $model) {
      $this->attempts->finish((string) $message->id(), $attemptId, new AssistantGenerationOutcome('', null, 'ollama_model_not_configured'));

      return;
    }

    try {
      $temperature = $command->temperature ?? self::DEFAULT_TEMPERATURE;
      $scope = new AssistantContextScope(actorUserId: $thread->memberId(), threadId: $command->threadId);
      $promptMessages = $this->promptBuilder->build(
        $this->completedTranscript($command->threadId, $command->assistantMessageId, $command->userMessageId),
        $command->organizationId,
        $scope,
        $this->includeBusinessContext($command->organizationId),
      );

      $remainingSeconds = max(1, ($message->attemptExpiresAt()?->getTimestamp() ?? PHP_INT_MAX) - $this->clock->now()->getTimestamp());

      $outcome = $this->client->streamChat(
        model: $model,
        messages: $promptMessages,
        temperature: $temperature,
        timeoutSeconds: min($this->timeoutSeconds, $remainingSeconds),
        onFragment: function (string $body) use ($message, $attemptId): void {
          if (!$this->attempts->fragment((string) $message->id(), $attemptId, $body)) {
            throw new AssistantGenerationStoppedException();
          }
        },
      );
    } catch (AssistantGenerationStoppedException) {
      return;
    } catch (Throwable $exception) {
      $this->logger->error('Assistant generation failed unexpectedly.', ['messageId' => (string) $message->id(), 'error' => $exception->getMessage()]);
      $outcome = new AssistantGenerationOutcome('', null, 'assistant_generation_failed');
    }
    $this->attempts->finish((string) $message->id(), $attemptId, $outcome);
  }

  /**
   * Method completedTranscript.
   *
   * Loads a thread's messages and keeps only the already-`complete` turns,
   * oldest first, excluding the reply currently being generated — a still
   * `pending`/`streaming`/`failed` assistant message is never replayed as
   * conversation history.
   *
   * @since 1.0.0
   *
   * @param string $threadId the owning thread identifier
   * @param string $excludedMessageId the currently-generating assistant message identifier to exclude
   *
   * @return list<AssistantMessage> the completed transcript, oldest first
   */
  private function completedTranscript(string $threadId, string $excludedMessageId, string $questionMessageId): array
  {
    $total = $this->messages->countByThread($threadId);
    $history = $this->messages->listByThread($threadId, $total, 0);

    foreach ($history as $index => $candidate) {
      if ((string) $candidate->id() === $questionMessageId) {
        $history = array_slice($history, 0, $index + 1);

        break;
      }
    }

    return array_values(array_filter(
      $history,
      static fn (AssistantMessage $candidate): bool => AssistantMessageStatus::COMPLETE === $candidate->status()
        && (string) $candidate->id() !== $excludedMessageId,
    ));
  }

  /**
   * Method includeBusinessContext.
   *
   * Resolves the organization's `settings.assistant.includeBusinessContext`
   * opt-in (L2.2). A lookup failure degrades to `false` (fail-closed, no
   * business context) rather than aborting generation — mirrors this
   * handler's own "never fail the question" posture for every other
   * external call.
   *
   * @since 1.0.0
   *
   * @param string $organizationId the owning organization identifier
   *
   * @return bool true when the organization has opted into business-context injection
   */
  private function includeBusinessContext(string $organizationId): bool
  {
    try {
      return $this->organizationSettings->includeBusinessContextFor($organizationId);
    } catch (Throwable $exception) {
      $this->logger->error('Failed to resolve the assistant business-context setting; degrading to no business context.', [
        'organizationId' => $organizationId,
        'error' => $exception->getMessage(),
      ]);

      return false;
    }
  }
  // #endregion
}
