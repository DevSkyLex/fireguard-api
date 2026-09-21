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

/** Service AssistantAttemptWriter. Every worker write and broadcast rechecks the current attempt under the HTTP decision lock. */
final readonly class AssistantAttemptWriter
{
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
      $message->markStreaming($now);
      $this->messages->save($message);

      return $message;
    });
  }

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
   * Caller holds the owning message lock, including cancellation/retry commands.
   */
  public function publish(AssistantMessage $message): void
  {
    try {
      $this->realtime->publishGenerationEvent($message->organizationId(), $message->threadId(), (string) $message->id(), $message->status()->value, $message->body(), $message->tokenCount(), $message->errorCode(), $message->attemptId() ?? (string) $message->id(), $message->attemptNumber(), $message->attemptSequence(), $message->attemptExpiresAt()?->format('c'));
    } catch (Throwable $exception) {
      $this->logger->warning('Assistant realtime publish failed.', ['messageId' => (string) $message->id(), 'error' => $exception->getMessage()]);
    }
  }

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
}
