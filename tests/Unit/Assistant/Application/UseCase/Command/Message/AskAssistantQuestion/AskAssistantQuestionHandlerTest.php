<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Assistant\Application\Port\Outbound\{AssistantGenerationDispatcherPort, AssistantMessageRepositoryPort, AssistantThreadRepositoryPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantAccessPolicy;
use Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\{AskAssistantQuestionCommand, AskAssistantQuestionHandler};
use Assistant\Domain\Event\Message\AssistantQuestionAskedEvent;
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\AssistantThreadId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

/**
 * Test AskAssistantQuestionHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AskAssistantQuestionHandler::class)]
final class AskAssistantQuestionHandlerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c05';

  private const string USER_MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c06';

  private const string ASSISTANT_MESSAGE_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c07';

  #[Test]
  public function testInvokeThrowsNotFoundWhenThreadMissing(): void
  {
    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn(null);

    $this->expectException(AssistantThreadNotFoundException::class);

    $this->handler(threads: $threads)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenThreadBelongsToAnotherMember(): void
  {
    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread('someone-else'));

    $this->expectException(AssistantThreadNotFoundException::class);

    $this->handler(threads: $threads)(self::command());
  }

  #[Test]
  public function testInvokePersistsBothMessagesEnqueuesGenerationAndDispatchesEvent(): void
  {
    $threads = $this->createMock(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread(self::USER_ID));
    $threads->expects(self::once())->method('save')->with(self::isInstanceOf(AssistantThread::class));

    $messages = $this->createMock(AssistantMessageRepositoryPort::class);
    $messages->expects(self::exactly(2))->method('save')->with(self::isInstanceOf(AssistantMessage::class));

    $dispatcher = $this->createMock(AssistantGenerationDispatcherPort::class);
    $dispatcher->expects(self::once())->method('enqueue')->with(
      self::ORG_ID,
      self::THREAD_ID,
      self::isString(),
      self::isString(),
      self::isNull(),
      self::isNull(),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(AssistantQuestionAskedEvent::class));

    $result = $this->handler(
      threads: $threads,
      messages: $messages,
      dispatcher: $dispatcher,
      eventDispatcher: $eventDispatcher,
    )(self::command());

    self::assertSame(self::THREAD_ID, $result->threadId);
    self::assertSame('user', $result->userMessage->role);
    self::assertSame('complete', $result->userMessage->status);
    self::assertSame('How many extinguishers are overdue?', $result->userMessage->body);
    self::assertSame('assistant', $result->assistantMessage->role);
    self::assertSame('pending', $result->assistantMessage->status);
  }

  #[Test]
  public function testInvokePassesTheThreadsModelAndTheCommandsTemperatureToTheDispatcher(): void
  {
    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($this->thread(self::USER_ID, 'llama3'));

    $dispatcher = $this->createMock(AssistantGenerationDispatcherPort::class);
    $dispatcher->expects(self::once())->method('enqueue')->with(
      self::ORG_ID,
      self::THREAD_ID,
      self::isString(),
      self::isString(),
      'llama3',
      0.9,
    );

    $command = new AskAssistantQuestionCommand(self::ORG_ID, self::THREAD_ID, self::USER_ID, 'How many extinguishers are overdue?', 0.9);

    $this->handler(threads: $threads, dispatcher: $dispatcher)($command);
  }

  private static function command(): AskAssistantQuestionCommand
  {
    return new AskAssistantQuestionCommand(self::ORG_ID, self::THREAD_ID, self::USER_ID, 'How many extinguishers are overdue?');
  }

  private function thread(string $memberId, ?string $model = null): AssistantThread
  {
    return AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: $memberId,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      model: $model,
    );
  }

  private function handler(
    ?AssistantThreadRepositoryPort $threads = null,
    ?AssistantMessageRepositoryPort $messages = null,
    ?AssistantGenerationDispatcherPort $dispatcher = null,
    ?AssistantAccessPolicy $accessPolicy = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): AskAssistantQuestionHandler {
    $threads ??= $this->createStub(AssistantThreadRepositoryPort::class);
    $messages ??= $this->createStub(AssistantMessageRepositoryPort::class);
    $dispatcher ??= $this->createStub(AssistantGenerationDispatcherPort::class);
    $accessPolicy ??= $this->enabledAccessPolicy();
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);

    $uuidGenerator = $this->createStub(UuidGeneratorPort::class);
    $uuidGenerator->method('generate')->willReturnOnConsecutiveCalls(self::USER_MESSAGE_ID, self::ASSISTANT_MESSAGE_ID);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-20T00:00:00+00:00'));

    return new AskAssistantQuestionHandler(
      threads: $threads,
      messages: $messages,
      dispatcher: $dispatcher,
      accessPolicy: $accessPolicy,
      uuidFactory: new UuidFactory($uuidGenerator),
      eventDispatcher: $eventDispatcher,
      clock: $clock,
    );
  }

  /**
   * Builds the real policy over doubled ports, with the organization toggle on.
   */
  private function enabledAccessPolicy(
    ?OrganizationAuthorizationPort $authorization = null,
    bool $assistantEnabled = true,
  ): AssistantAccessPolicy {
    $settings = $this->createStub(AssistantOrganizationSettingsPort::class);
    $settings->method('isEnabledFor')->willReturn($assistantEnabled);

    return new AssistantAccessPolicy(
      $authorization ?? $this->createStub(OrganizationAuthorizationPort::class),
      $settings,
    );
  }
}
