<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Query\Thread\GetAssistantThread;

use Assistant\Application\Port\Outbound\{AssistantMessageRepositoryPort, AssistantThreadRepositoryPort};
use Assistant\Application\Port\Outbound\Organization\AssistantOrganizationSettingsPort;
use Assistant\Application\Service\AssistantAccessPolicy;
use Assistant\Application\UseCase\Query\Thread\GetAssistantThread\{GetAssistantThreadHandler, GetAssistantThreadQuery};
use Assistant\Domain\Exception\AssistantThreadNotFoundException;
use Assistant\Domain\Model\Message\AssistantMessage;
use Assistant\Domain\Model\Thread\AssistantThread;
use Assistant\Domain\ValueObject\{AssistantMessageId, AssistantThreadId};
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetAssistantThreadHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetAssistantThreadHandler::class)]
final class GetAssistantThreadHandlerTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c03';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c04';

  private const string THREAD_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c05';

  #[Test]
  public function testInvokeThrowsNotFoundWhenThreadMissing(): void
  {
    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn(null);

    $this->expectException(AssistantThreadNotFoundException::class);

    $this->handler(threads: $threads)(self::query());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenThreadBelongsToAnotherOrganization(): void
  {
    $thread = $this->thread(self::USER_ID);

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($thread);

    $this->expectException(AssistantThreadNotFoundException::class);

    $this->handler(threads: $threads)(new GetAssistantThreadQuery('another-org', self::THREAD_ID, self::USER_ID));
  }

  #[Test]
  public function testInvokeReturnsTheThreadWithItsMessagesInChronologicalOrder(): void
  {
    $thread = $this->thread(self::USER_ID);

    $userMessage = AssistantMessage::askUser(
      id: AssistantMessageId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64c06'),
      threadId: self::THREAD_ID,
      organizationId: self::ORG_ID,
      body: 'How many extinguishers are overdue?',
      now: new DateTimeImmutable('2026-01-01T00:00:01+00:00'),
    );

    $threads = $this->createStub(AssistantThreadRepositoryPort::class);
    $threads->method('findById')->willReturn($thread);

    $messages = $this->createMock(AssistantMessageRepositoryPort::class);
    $messages->expects(self::once())
      ->method('listByThread')
      ->with(self::THREAD_ID, 50, 0)
      ->willReturn([$userMessage]);
    $messages->method('countByThread')->with(self::THREAD_ID)->willReturn(1);

    $result = $this->handler(threads: $threads, messages: $messages)(self::query());

    self::assertSame(self::THREAD_ID, $result->thread->id);
    self::assertCount(1, $result->messages);
    self::assertSame('How many extinguishers are overdue?', $result->messages[0]->body);
    self::assertSame(1, $result->messagesTotal);
  }

  private static function query(): GetAssistantThreadQuery
  {
    return new GetAssistantThreadQuery(self::ORG_ID, self::THREAD_ID, self::USER_ID);
  }

  private function thread(string $memberId): AssistantThread
  {
    return AssistantThread::start(
      id: AssistantThreadId::fromString(self::THREAD_ID),
      organizationId: self::ORG_ID,
      memberId: $memberId,
      title: null,
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
  }

  private function handler(
    ?AssistantThreadRepositoryPort $threads = null,
    ?AssistantMessageRepositoryPort $messages = null,
    ?AssistantAccessPolicy $accessPolicy = null,
  ): GetAssistantThreadHandler {
    $threads ??= $this->createStub(AssistantThreadRepositoryPort::class);
    $messages ??= $this->createStub(AssistantMessageRepositoryPort::class);
    $accessPolicy ??= $this->enabledAccessPolicy();

    return new GetAssistantThreadHandler($threads, $messages, $accessPolicy);
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
