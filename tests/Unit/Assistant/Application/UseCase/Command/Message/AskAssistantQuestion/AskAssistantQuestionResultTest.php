<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Application\UseCase\Command\Message\AskAssistantQuestion;

use Assistant\Application\Contract\Message\AssistantMessageView;
use Assistant\Application\UseCase\Command\Message\AskAssistantQuestion\AskAssistantQuestionResult;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AskAssistantQuestionResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AskAssistantQuestionResult::class)]
final class AskAssistantQuestionResultTest extends TestCase
{
  #[Test]
  public function testExposesEveryProperty(): void
  {
    $userMessage = $this->view('user');
    $assistantMessage = $this->view('assistant');

    $result = new AskAssistantQuestionResult(
      threadId: 'thread-2',
      organizationId: 'org-1',
      userMessage: $userMessage,
      assistantMessage: $assistantMessage,
    );

    self::assertSame('thread-2', $result->threadId);
    self::assertSame('org-1', $result->organizationId);
    self::assertSame($userMessage, $result->userMessage);
    self::assertSame($assistantMessage, $result->assistantMessage);
  }

  private function view(string $role): AssistantMessageView
  {
    return new AssistantMessageView(
      id: 'msg-' . $role,
      threadId: 'thread-2',
      organizationId: 'org-1',
      role: $role,
      body: 'body',
      status: 'complete',
      errorCode: null,
      tokenCount: null,
      createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      completedAt: null,
    );
  }
}
