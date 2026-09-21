<?php

declare(strict_types=1);

namespace Assistant\Application\UseCase\Command\Message\ControlAssistantAttempt;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\Port\Outbound\{AssistantAttemptLockPort, AssistantGenerationDispatcherPort, AssistantMessageRepositoryPort, AssistantThreadRepositoryPort};
use Assistant\Application\Service\{AssistantAccessPolicy, AssistantAttemptWriter};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use Shared\Application\Message\CommandHandler;
use Shared\Application\Port\Outbound\{ClockPort, UuidGeneratorPort};

/** Handler ControlAssistantAttemptHandler. Retry keeps the question and message, and atomically queues one new attempt. */
final readonly class ControlAssistantAttemptHandler implements CommandHandler
{
  public function __construct(private AssistantAttemptLockPort $lock, private AssistantMessageRepositoryPort $messages, private AssistantThreadRepositoryPort $threads, private AssistantAccessPolicy $access, private AssistantGenerationDispatcherPort $dispatcher, private AssistantAttemptWriter $writer, private ClockPort $clock, private UuidGeneratorPort $ids)
  {
  }

  public function __invoke(ControlAssistantAttemptCommand $command): ControlAssistantAttemptResult
  {
    return $this->lock->synchronized($command->messageId, function () use ($command): ControlAssistantAttemptResult {
      $this->access->assertCanUseAssistant($command->actorUserId, $command->organizationId);
      $thread = $this->threads->findById(AssistantThreadId::fromString($command->threadId));
      $message = $this->messages->findById(AssistantMessageId::fromString($command->messageId));
      if (null === $thread || !$thread->belongsTo($command->organizationId, $command->actorUserId) || null === $message || $message->organizationId() !== $command->organizationId || $message->threadId() !== $command->threadId) {
        throw AssistantThreadNotFoundException::withId($command->threadId);
      }
      $now = $this->clock->now();
      if ($command->retry) {
        $message->retry($command->attemptId, $this->ids->generate(), $now);
        $this->messages->save($message);
        $this->dispatcher->enqueue($command->organizationId, $command->threadId, $message->questionMessageId() ?? '', $command->messageId, $thread->model(), $message->temperature(), $message->attemptId());
      } else {
        $message->cancel($command->attemptId, $now);
        $this->messages->save($message);
      }
      $this->writer->publish($message);

      return new ControlAssistantAttemptResult(AssistantMessageView::fromDomain($message));
    });
  }
}
