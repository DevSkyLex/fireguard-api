<?php

declare(strict_types=1);

namespace Assistant\Application\Service;

use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use Assistant\Application\Port\Outbound\{AssistantAttemptLockPort, AssistantMessageRepositoryPort, AssistantRealtimePublisherPort, AssistantThreadRepositoryPort};
use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\GenerateAssistantReplyCommand;
use Assistant\Domain\Event\Message\AssistantReplyGeneratedEvent;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageStatus, AssistantThreadId};
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort};
use Throwable;

/**
 * Class AssistantAttemptWriter
 *
 * Guards worker writes and broadcasts by rechecking the current attempt under the message lock.
 *
 * @category Service
 */
final readonly class AssistantAttemptWriter
{
  // #region Constructor
  /**
   * Method __construct
   *
   * Supplies locking, message/thread persistence, realtime, clock, event and logging capabilities.
   *
   * @access public
   *
   * @param AssistantAttemptLockPort $lock serializes worker updates with decisions
   * @param AssistantMessageRepositoryPort $messages reads and saves assistant messages
   * @param AssistantThreadRepositoryPort $threads reads and saves assistant threads
   * @param AssistantRealtimePublisherPort $realtime publishes generation updates
   * @param ClockPort $clock provides attempt timestamps
   * @param EventDispatcherPort $events dispatches completed-reply events
   * @param LoggerPort $logger records realtime publication failures
   *
   * @return void
   */
  public function __construct(
    private AssistantAttemptLockPort $lock,
    private AssistantMessageRepositoryPort $messages,
    private AssistantThreadRepositoryPort $threads,
    private AssistantRealtimePublisherPort $realtime,
    private ClockPort $clock,
    private EventDispatcherPort $events,
    private LoggerPort $logger,
  ) {
  }

  // #endregion

  // #region Methods
  /**
   * Method claim
   *
   * Claims a pending message for the matching attempt and starts streaming unless it expired.
   *
   * @access public
   *
   * @param GenerateAssistantReplyCommand $command generation attempt and message context
   *
   * @return AssistantMessage|null claimed streaming message, or null when it cannot be claimed
   */
  public function claim(GenerateAssistantReplyCommand $command): ?AssistantMessage
  {
    return $this->lock->synchronized($command->assistantMessageId, function () use ($command): ?AssistantMessage {
      $message = $this->messages->findById(AssistantMessageId::fromString($command->assistantMessageId));
      if (null === $message || $message->threadId() !== $command->threadId || $message->organizationId() !== $command->organizationId || !$message->matchesAttempt($command->attemptId ?? $command->assistantMessageId) || !$message->isPending()) {
        return null;
      }
      $now = $this->clock->now();
      $message->initializeAttempt($command->userMessageId, $command->temperature, $now);
      if ($message->isExpired($now)) {
        $message->markFailed('assistant_attempt_expired', $now);
        $this->messages->save($message);
        $this->publish($message);

        return null;
      }
      $message->markStreaming();
      $this->messages->save($message);

      return $message;
    });
  }

  /**
   * Method fragment
   *
   * Appends and publishes a generated fragment only while its attempt remains active.
   *
   * @access public
   *
   * @param string $messageId assistant message identifier
   * @param string $attemptId generation attempt identifier
   * @param string $body generated fragment to append
   *
   * @return bool whether the fragment was accepted for the active attempt
   */
  public function fragment(string $messageId, string $attemptId, string $body): bool
  {
    return $this->lock->synchronized($messageId, function () use ($messageId, $attemptId, $body): bool {
      $message = $this->active($messageId, $attemptId);
      if (null === $message) {
        return false;
      }
      $message->recordFragment($body);
      $this->messages->save($message);
      $this->publish($message);

      return true;
    });
  }

  /**
   * Method finish
   *
   * Completes or fails the active attempt, updates thread activity on success and emits its event.
   *
   * @access public
   *
   * @param string $messageId assistant message identifier
   * @param string $attemptId generation attempt identifier
   * @param AssistantGenerationOutcome $outcome generation result to persist
   *
   * @return void
   */
  public function finish(string $messageId, string $attemptId, AssistantGenerationOutcome $outcome): void
  {
    $this->lock->synchronized($messageId, function () use ($messageId, $attemptId, $outcome): void {
      $message = $this->active($messageId, $attemptId);
      if (null === $message) {
        return;
      }
      $now = $this->clock->now();
      if ($outcome->isSuccessful()) {
        $message->markComplete($outcome->body, $outcome->tokenCount, $now);
      } else {
        $message->markFailed($outcome->errorCode ?? 'ollama_generation_failed', $now);
      }
      $this->messages->save($message);
      $this->publish($message);
      if (!$outcome->isSuccessful()) {
        return;
      }
      $thread = $this->threads->findById(AssistantThreadId::fromString($message->threadId()));
      if (null !== $thread) {
        $thread->recordActivity($now);
        $this->threads->save($thread);
      }
      $this->events->dispatch(new AssistantReplyGeneratedEvent($message->organizationId(), $message->threadId(), (string) $message->id(), $message->tokenCount()));
    });
  }

  /**
   * Method publish
   *
   * Broadcasts the current generation state and logs publication failures without interrupting work.
   * The caller holds the owning message lock, including for cancellation and retry commands.
   *
   * @access public
   *
   * @param AssistantMessage $message message state to publish
   *
   * @return void
   */
  public function publish(AssistantMessage $message): void
  {
    try {
      $this->realtime->publishGenerationEvent($message);
    } catch (Throwable $exception) {
      $this->logger->warning('Assistant realtime publish failed.', ['messageId' => (string) $message->id(), 'error' => $exception->getMessage()]);
    }
  }

  /**
   * Method active
   *
   * Loads a message only when the attempt matches, remains streaming and has not expired.
   * Expired attempts are marked failed and published before returning null.
   *
   * @access private
   *
   * @param string $messageId assistant message identifier
   * @param string $attemptId generation attempt identifier
   *
   * @return AssistantMessage|null active streaming message, or null when stale or expired
   */
  private function active(string $messageId, string $attemptId): ?AssistantMessage
  {
    $message = $this->messages->findById(AssistantMessageId::fromString($messageId));
    if (null === $message || !$message->matchesAttempt($attemptId) || AssistantMessageStatus::STREAMING !== $message->status()) {
      return null;
    }
    $now = $this->clock->now();
    if ($message->isExpired($now)) {
      $message->markFailed('assistant_attempt_expired', $now);
      $this->messages->save($message);
      $this->publish($message);

      return null;
    }

    return $message;
  }
  // #endregion
}
