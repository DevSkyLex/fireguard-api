<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Message\GenerateAssistantReply;

use Assistant\Application\Contract\Context\{AssistantContextBudget, AssistantContextFragment, AssistantContextScope};
use Assistant\Application\Contract\Generation\AssistantGenerationOutcome;
use Assistant\Application\Port\Outbound\{AssistantContextProviderPort, AssistantGenerationClientPort, AssistantMessageRepositoryPort, AssistantRealtimePublisherPort, AssistantThreadRepositoryPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\{AssistantContextAssembler, AssistantPromptBuilder};
use Assistant\Application\UseCase\Command\Message\GenerateAssistantReply\{GenerateAssistantReplyCommand, GenerateAssistantReplyHandler};
use Assistant\Domain\Event\Message\AssistantReplyGeneratedEvent;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantMessageRole, AssistantMessageStatus, AssistantThreadId};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, LoggerPort};

/**
 * Test GenerateAssistantReplyHandlerTest.
 *
 * The crux: a retry that finds the message already `streaming` must NOT
 * re-append/duplicate — `markComplete()`/`markFailed()` REPLACE the row, and
 * every published Mercure fragment carries the full accumulated body, never
 * a delta.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GenerateAssistantReplyHandler::class)]
final class GenerateAssistantReplyHandlerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d01';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d02';

  private const string USER_MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d03';

  private const string ASSISTANT_MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d04';

  #[Test]
  public function testInvokeIsANoOpWhenTheMessageIsNotFound(): void
  {
    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn(null);

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())->method('error');

    $client = $this->createMock(AssistantGenerationClientPort::class);
    $client->expects(self::never())->method('streamChat');

    $this->handler(messages: $messages, logger: $logger, client: $client)(self::command());
  }

  #[Test]
  public function testInvokeIsANoOpWhenTheMessageIsAlreadyComplete(): void
  {
    $message = $this->completeMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);

    $client = $this->createMock(AssistantGenerationClientPort::class);
    $client->expects(self::never())->method('streamChat');

    $this->handler(messages: $messages, client: $client)(self::command());
  }

  #[Test]
  public function testInvokeStreamsRepublishesFragmentsAndMarksComplete(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createMock(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $messages->method('listByThread')->willReturn([]);
    $messages->method('countByThread')->willReturn(0);
    $messages->expects(self::exactly(2))->method('save')->with($message);

    $thread = $this->thread();
    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($thread);
    $threads->expects(self::once())->method('save')->with($thread);

    $client = $this->createStub(AssistantGenerationClientPort::class);
    $client->method('streamChat')->willReturnCallback(
      static function (string $model, array $promptMessages, float $temperature, int $timeout, callable $onFragment): AssistantGenerationOutcome {
        $onFragment('Hel');
        $onFragment('Hello');

        return new AssistantGenerationOutcome('Hello', 5);
      },
    );

    $publishedEvents = [];
    $realtime = $this->createMock(AssistantRealtimePublisherPort::class);
    $realtime->expects(self::exactly(3))
      ->method('publishGenerationEvent')
      ->willReturnCallback(static function (...$args) use (&$publishedEvents): void {
        $publishedEvents[] = $args;
      });

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(AssistantReplyGeneratedEvent::class));

    $this->handler(
      messages: $messages,
      threads: $threads,
      client: $client,
      realtime: $realtime,
      eventDispatcher: $eventDispatcher,
    )(self::command());

    self::assertSame(AssistantMessageStatus::COMPLETE, $message->status());
    self::assertSame('Hello', $message->body());
    self::assertSame(5, $message->tokenCount());

    // Every published event's body is the FULL accumulated text so far.
    self::assertSame('Hel', $publishedEvents[0][4]);
    self::assertSame('Hello', $publishedEvents[1][4]);
    self::assertSame('Hello', $publishedEvents[2][4]);
    self::assertSame('complete', $publishedEvents[2][3]);
  }

  #[Test]
  public function testARetryThatFindsTheMessageAlreadyStreamingDoesNotDuplicateAndStillCompletes(): void
  {
    // Simulates a previous attempt that crashed after markStreaming() but
    // before markComplete(): the message is loaded already STREAMING.
    $message = $this->streamingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $messages->method('listByThread')->willReturn([]);
    $messages->method('countByThread')->willReturn(0);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread());

    $client = $this->createStub(AssistantGenerationClientPort::class);
    $client->method('streamChat')->willReturnCallback(
      static function (string $model, array $promptMessages, float $temperature, int $timeout, callable $onFragment): AssistantGenerationOutcome {
        $onFragment('Re-hel');
        $onFragment('Re-hello');

        return new AssistantGenerationOutcome('Re-hello', 3);
      },
    );

    // markStreaming() is never re-invoked (it would throw, since the legal
    // transition only accepts pending -> streaming) — this does not throw
    // and the message settles complete.
    $this->handler(messages: $messages, threads: $threads, client: $client)(self::command());

    self::assertSame(AssistantMessageStatus::COMPLETE, $message->status());
    self::assertSame('Re-hello', $message->body());
  }

  #[Test]
  public function testInvokeMarksFailedWhenGenerationFails(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $messages->method('listByThread')->willReturn([]);
    $messages->method('countByThread')->willReturn(0);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread());

    $client = $this->createStub(AssistantGenerationClientPort::class);
    $client->method('streamChat')->willReturn(new AssistantGenerationOutcome('', null, 'ollama_unreachable', 'Connection refused'));

    $this->handler(messages: $messages, threads: $threads, client: $client)(self::command());

    self::assertSame(AssistantMessageStatus::FAILED, $message->status());
    self::assertSame('ollama_unreachable', $message->errorCode());
  }

  #[Test]
  public function testInvokeMarksFailedWhenTheThreadNoLongerExists(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn(null);

    $client = $this->createMock(AssistantGenerationClientPort::class);
    $client->expects(self::never())->method('streamChat');

    $this->handler(messages: $messages, threads: $threads, client: $client)(self::command());

    self::assertSame(AssistantMessageStatus::FAILED, $message->status());
    self::assertSame('assistant_thread_not_found', $message->errorCode());
  }

  #[Test]
  public function testInvokeMarksFailedWhenNoModelIsConfiguredAnywhere(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread());

    $client = $this->createMock(AssistantGenerationClientPort::class);
    $client->expects(self::never())->method('streamChat');

    $this->handler(messages: $messages, threads: $threads, client: $client, defaultModel: '')(self::command());

    self::assertSame(AssistantMessageStatus::FAILED, $message->status());
    self::assertSame('ollama_model_not_configured', $message->errorCode());
  }

  #[Test]
  public function testInvokeInjectsBusinessContextWhenTheOrganizationHasOptedIn(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $messages->method('listByThread')->willReturn([]);
    $messages->method('countByThread')->willReturn(0);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread());

    $organizationSettings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $organizationSettings->method('includeBusinessContextFor')->willReturn(true);

    $capturedPromptMessages = null;
    $client = $this->createStub(AssistantGenerationClientPort::class);
    $client->method('streamChat')->willReturnCallback(
      static function (string $model, array $promptMessages, float $temperature, int $timeout, callable $onFragment) use (&$capturedPromptMessages): AssistantGenerationOutcome {
        $capturedPromptMessages = $promptMessages;
        $onFragment('Hello');

        return new AssistantGenerationOutcome('Hello', 1);
      },
    );

    $this->handler(
      messages: $messages,
      threads: $threads,
      client: $client,
      organizationSettings: $organizationSettings,
    )(self::command());

    self::assertNotNull($capturedPromptMessages);
    // system prompt + the fake provider's context block, both BEFORE the
    // (empty) transcript.
    self::assertCount(2, $capturedPromptMessages);
    $contextMessage = $capturedPromptMessages[1];
    self::assertIsArray($contextMessage);
    self::assertSame('system', $contextMessage['role']);
    self::assertSame('Fake business context.', $contextMessage['content']);
  }

  #[Test]
  public function testInvokeNeverInjectsBusinessContextWhenTheOrganizationHasNotOptedIn(): void
  {
    $message = $this->pendingMessage();

    $messages = $this->createStub(AssistantMessageRepositoryPort::class);
    $messages->method('findById')->willReturn($message);
    $messages->method('listByThread')->willReturn([]);
    $messages->method('countByThread')->willReturn(0);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread());

    $organizationSettings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $organizationSettings->method('includeBusinessContextFor')->willReturn(false);

    $capturedPromptMessages = null;
    $client = $this->createStub(AssistantGenerationClientPort::class);
    $client->method('streamChat')->willReturnCallback(
      static function (string $model, array $promptMessages, float $temperature, int $timeout, callable $onFragment) use (&$capturedPromptMessages): AssistantGenerationOutcome {
        $capturedPromptMessages = $promptMessages;
        $onFragment('Hello');

        return new AssistantGenerationOutcome('Hello', 1);
      },
    );

    $this->handler(
      messages: $messages,
      threads: $threads,
      client: $client,
      organizationSettings: $organizationSettings,
    )(self::command());

    self::assertNotNull($capturedPromptMessages);
    self::assertCount(1, $capturedPromptMessages, 'The fake provider is registered but must never be reached when the flag is off.');
  }

  private static function command(): GenerateAssistantReplyCommand
  {
    return new GenerateAssistantReplyCommand(self::ORG_ID, self::THREAD_ID, self::USER_MESSAGE_ID, self::ASSISTANT_MESSAGE_ID);
  }

  private function pendingMessage(): AssistantMessage
  {
    return AssistantMessage::pendingReply(
      id: AssistantMessageId::fromString(self::ASSISTANT_MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function streamingMessage(): AssistantMessage
  {
    return AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString(self::ASSISTANT_MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      role: AssistantMessageRole::ASSISTANT,
      body: '',
      status: AssistantMessageStatus::STREAMING,
      errorCode: null,
      tokenCount: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      completedAt: null,
    );
  }

  private function completeMessage(): AssistantMessage
  {
    return AssistantMessage::reconstitute(
      id: AssistantMessageId::fromString(self::ASSISTANT_MESSAGE_ID),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      role: AssistantMessageRole::ASSISTANT,
      body: 'Already answered.',
      status: AssistantMessageStatus::COMPLETE,
      errorCode: null,
      tokenCount: 3,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      completedAt: new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
    );
  }

  private function thread(): AssistantThread
  {
    return AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: 'member-1',
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function handler(
    ?AssistantMessageRepositoryPort $messages = null,
    ?AssistantThreadRepositoryPort $threads = null,
    ?AssistantGenerationClientPort $client = null,
    ?AssistantRealtimePublisherPort $realtime = null,
    ?EventDispatcherPort $eventDispatcher = null,
    ?LoggerPort $logger = null,
    ?AssistantOrganizationSettingsPort $organizationSettings = null,
    string $defaultModel = 'llama3',
  ): GenerateAssistantReplyHandler {
    $messages ??= $this->createStub(AssistantMessageRepositoryPort::class);
    $threads ??= $this->createStub(AssistantThreadRepositoryPort::class);
    $client ??= $this->createStub(AssistantGenerationClientPort::class);
    $realtime ??= $this->createStub(AssistantRealtimePublisherPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);
    $logger ??= $this->createStub(LoggerPort::class);
    // Defaults to "not opted in" (false) so every existing scenario keeps
    // exercising the same empty-context prompt it always has — L2.2 adds a
    // DEDICATED test below for the true path.
    $organizationSettings ??= $this->createStub(AssistantOrganizationSettingsPort::class);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-20T00:00:00+00:00'));

    // A single always-supporting fake provider — only reached when
    // `$organizationSettings->includeBusinessContextFor()` returns true (see
    // `testInvokeInjectsBusinessContextWhenTheOrganizationHasOptedIn()` /
    // `testInvokeNeverInjectsBusinessContextWhenTheOrganizationHasNotOptedIn()`).
    $promptBuilder = new AssistantPromptBuilder(new AssistantContextAssembler([$this->fakeContextProvider()], $logger));

    return new GenerateAssistantReplyHandler(
      messages: $messages,
      threads: $threads,
      client: $client,
      realtime: $realtime,
      promptBuilder: $promptBuilder,
      clock: $clock,
      eventDispatcher: $eventDispatcher,
      logger: $logger,
      organizationSettings: $organizationSettings,
      defaultModel: $defaultModel,
      timeoutSeconds: 30,
    );
  }

  private function fakeContextProvider(): AssistantContextProviderPort
  {
    return new class () implements AssistantContextProviderPort {
      public function supports(string $organizationId, AssistantContextScope $scope): bool
      {
        return true;
      }

      public function provide(string $organizationId, AssistantContextScope $scope, AssistantContextBudget $budget): AssistantContextFragment
      {
        return AssistantContextFragment::withText('fake', 'Fake business context.');
      }
    };
  }
}
