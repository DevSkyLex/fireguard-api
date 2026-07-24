<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\GenerateAssistantReply;

use Assistant\Application\Contract\Context\AssistantContextScope;
use Assistant\Application\Port\Outbound\{AssistantGenerationClientPort, AssistantMessageRepositoryPort, AssistantRealtimePublisherPort, AssistantThreadRepositoryPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantPromptBuilder;
use Assistant\Domain\Event\Message\AssistantReplyGeneratedEvent;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageStatus, AssistantThreadId};
use DateTimeImmutable;
use Shared\Application\Message\{CommandHandler, VoidResult};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort};
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

use function array_filter;
use function array_values;

/**
 * UseCase GenerateAssistantReplyHandler.
 *
 * Consumes the `assistant` Messenger transport. Loads the pending assistant
 * reply, assembles the prompt (system prompt + completed transcript, via
 * {@see AssistantPromptBuilder}), and streams a chat completion through
 * {@see AssistantGenerationClientPort}, republishing every fragment to
 * Mercure via {@see AssistantRealtimePublisherPort} as it arrives.
 *
 * **Idempotent on Messenger redelivery (the crux)**: a message already
 * {@see \Assistant\Domain\ValueObject\AssistantMessageStatus::isTerminal()}
 * (`complete`/`failed`) is a safe no-op — mirrors
 * `Webhook\Application\UseCase\Command\Delivery\DeliverWebhookHandler`. A
 * retry that finds the message already `streaming` (a previous attempt
 * crashed mid-stream) does NOT re-call `markStreaming()` (illegal —
 * {@see AssistantMessage} only allows that transition from `pending`) and
 * restarts generation from scratch; this is safe rather than
 * user-visible-duplicating because every published Mercure fragment carries
 * the FULL accumulated body so far, never a delta — the client only ever
 * REPLACES its displayed text, exactly like
 * {@see AssistantMessage::markComplete()} replaces (never appends to) the
 * persisted row.
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
   * @param AssistantRealtimePublisherPort $realtime the Mercure realtime publisher port
   * @param AssistantPromptBuilder $promptBuilder the prompt builder
   * @param ClockPort $clock the clock port
   * @param EventDispatcherPort $eventDispatcher the domain event dispatcher
   * @param LoggerPort $logger the logger port
   * @param AssistantOrganizationSettingsPort $organizationSettings the organization assistant settings port (L2.2: `includeBusinessContext` gate)
   * @param string $defaultModel the operator's default model, used when neither the request nor the thread selects one
   * @param int $timeoutSeconds the outbound request timeout, in seconds
   */
  public function __construct(
    private AssistantMessageRepositoryPort $messages,
    private AssistantThreadRepositoryPort $threads,
    private AssistantGenerationClientPort $client,
    private AssistantRealtimePublisherPort $realtime,
    private AssistantPromptBuilder $promptBuilder,
    private ClockPort $clock,
    private EventDispatcherPort $eventDispatcher,
    private LoggerPort $logger,
    private AssistantOrganizationSettingsPort $organizationSettings,
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
    $message = $this->messages->findById(AssistantMessageId::fromString($command->assistantMessageId));

    if (null === $message) {
      $this->logger->error('Assistant message not found for generation attempt.', [
        'assistantMessageId' => $command->assistantMessageId,
        'threadId' => $command->threadId,
      ]);

      return new VoidResult();
    }

    if ($message->status()->isTerminal()) {
      // Already complete/failed: a routine, safe no-op on Messenger redelivery.
      return new VoidResult();
    }

    $thread = $this->threads->findById(AssistantThreadId::fromString($command->threadId));

    if (null === $thread) {
      $this->failMessage($message, 'assistant_thread_not_found');

      return new VoidResult();
    }

    $model = $command->model ?? $thread->model() ?? $this->defaultModel;

    if ('' === $model) {
      $this->failMessage($message, 'ollama_model_not_configured');

      return new VoidResult();
    }

    $temperature = $command->temperature ?? self::DEFAULT_TEMPERATURE;
    $scope = new AssistantContextScope(actorUserId: $thread->memberId(), threadId: $command->threadId);
    $promptMessages = $this->promptBuilder->build(
      $this->completedTranscript($command->threadId, $command->assistantMessageId),
      $command->organizationId,
      $scope,
      $this->includeBusinessContext($command->organizationId),
    );

    $streamingStarted = !$message->isPending();

    $outcome = $this->client->streamChat(
      model: $model,
      messages: $promptMessages,
      temperature: $temperature,
      timeoutSeconds: $this->timeoutSeconds,
      onFragment: function (string $accumulatedBody) use ($message, $command, &$streamingStarted): void {
        if (!$streamingStarted) {
          $message->markStreaming($this->clock->now());
          $this->messages->save($message);
          $streamingStarted = true;
        }

        $this->publishGeneration(
          $command->organizationId,
          $command->threadId,
          $command->assistantMessageId,
          AssistantMessageStatus::STREAMING->value,
          $accumulatedBody,
        );
      },
    );

    $now = $this->clock->now();

    if (!$outcome->isSuccessful()) {
      $this->failMessage($message, $outcome->errorCode ?? 'ollama_generation_failed', $now);

      return new VoidResult();
    }

    $message->markComplete($outcome->body, $outcome->tokenCount, $now);
    $this->messages->save($message);

    $this->publishGeneration(
      $command->organizationId,
      $command->threadId,
      $command->assistantMessageId,
      AssistantMessageStatus::COMPLETE->value,
      $message->body(),
      $message->tokenCount(),
    );

    $thread->recordActivity($now);
    $this->threads->save($thread);

    $this->eventDispatcher->dispatch(new AssistantReplyGeneratedEvent(
      organizationId: $command->organizationId,
      threadId: $command->threadId,
      assistantMessageId: $command->assistantMessageId,
      tokenCount: $message->tokenCount(),
    ));

    return new VoidResult();
  }

  /**
   * Method publishGeneration.
   *
   * Publishes one generation event, absorbing any hub failure.
   *
   * The publish is not allowed to abort the handler: the row's status has
   * already been persisted by the time this runs, and an unguarded throw here
   * strands the reply at `streaming` forever — the member sees a spinner that
   * never resolves and no retry ever settles it. Mirrors the guard
   * `PostMessageHandler` already applies to its own realtime publish.
   *
   * @since 1.1.0
   *
   * @param string $organizationId the owning organization identifier
   * @param string $threadId the owning thread identifier
   * @param string $assistantMessageId the assistant message identifier
   * @param string $status the message status to publish
   * @param string $body the accumulated body
   * @param ?int $tokenCount the token count, when settled
   * @param ?string $errorCode a stable failure code, when failed
   */
  private function publishGeneration(
    string $organizationId,
    string $threadId,
    string $assistantMessageId,
    string $status,
    string $body,
    ?int $tokenCount = null,
    ?string $errorCode = null,
  ): void {
    try {
      $this->realtime->publishGenerationEvent(
        $organizationId,
        $threadId,
        $assistantMessageId,
        $status,
        $body,
        $tokenCount,
        $errorCode,
      );
    } catch (Throwable $exception) {
      $this->logger->warning('Assistant realtime publish failed.', [
        'threadId' => $threadId,
        'assistantMessageId' => $assistantMessageId,
        'status' => $status,
        'error' => $exception->getMessage(),
      ]);
    }
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
  private function completedTranscript(string $threadId, string $excludedMessageId): array
  {
    $total = $this->messages->countByThread($threadId);
    $history = $this->messages->listByThread($threadId, $total, 0);

    return array_values(array_filter(
      $history,
      static fn (AssistantMessage $candidate): bool => AssistantMessageStatus::COMPLETE === $candidate->status()
        && (string) $candidate->id() !== $excludedMessageId,
    ));
  }

  /**
   * Method failMessage.
   *
   * Marks the assistant message terminally failed and republishes the
   * failure to Mercure. A safe no-op when the message somehow already
   * settled between the earlier terminal-status check and this call.
   *
   * @since 1.0.0
   *
   * @param AssistantMessage $message the assistant message to fail
   * @param string $errorCode a stable failure code
   * @param ?DateTimeImmutable $now the current time, if already resolved
   */
  private function failMessage(AssistantMessage $message, string $errorCode, ?DateTimeImmutable $now = null): void
  {
    if ($message->status()->isTerminal()) {
      return;
    }

    $now ??= $this->clock->now();
    $message->markFailed($errorCode, $now);
    $this->messages->save($message);

    $this->publishGeneration(
      $message->organizationId(),
      $message->threadId(),
      (string) $message->id(),
      AssistantMessageStatus::FAILED->value,
      $message->body(),
      null,
      $errorCode,
    );
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
