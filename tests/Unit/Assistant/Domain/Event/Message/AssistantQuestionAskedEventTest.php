<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Event\Message;

use Assistant\Domain\Event\Message\AssistantQuestionAskedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantQuestionAskedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantQuestionAskedEvent::class)]
final class AssistantQuestionAskedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAndStampsOccurredAt(): void
  {
    $event = new AssistantQuestionAskedEvent(
      organizationId: 'org-1',
      threadId: 'thread-2',
      memberId: 'member-3',
      userMessageId: 'user-msg-4',
      assistantMessageId: 'assistant-msg-5',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('thread-2', $event->threadId);
    self::assertSame('member-3', $event->memberId);
    self::assertSame('user-msg-4', $event->userMessageId);
    self::assertSame('assistant-msg-5', $event->assistantMessageId);
    self::assertNotSame('', $event->occurredAt->format('c'));
  }
}
