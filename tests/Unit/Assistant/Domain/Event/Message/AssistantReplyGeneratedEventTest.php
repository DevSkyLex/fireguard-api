<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Event\Message;

use Assistant\Domain\Event\Message\AssistantReplyGeneratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantReplyGeneratedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantReplyGeneratedEvent::class)]
final class AssistantReplyGeneratedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadWithTokenCount(): void
  {
    $event = new AssistantReplyGeneratedEvent(
      organizationId: 'org-1',
      threadId: 'thread-2',
      assistantMessageId: 'assistant-msg-3',
      tokenCount: 128,
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('thread-2', $event->threadId);
    self::assertSame('assistant-msg-3', $event->assistantMessageId);
    self::assertSame(128, $event->tokenCount);
    self::assertNotSame('', $event->occurredAt->format('c'));
  }

  #[Test]
  public function testAllowsUnknownTokenCount(): void
  {
    $event = new AssistantReplyGeneratedEvent(
      organizationId: 'org-1',
      threadId: 'thread-2',
      assistantMessageId: 'assistant-msg-3',
      tokenCount: null,
    );

    self::assertNull($event->tokenCount);
  }
}
