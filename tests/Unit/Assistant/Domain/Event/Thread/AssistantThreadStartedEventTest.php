<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\Event\Thread;

use Assistant\Domain\Event\Thread\AssistantThreadStartedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantThreadStartedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantThreadStartedEvent::class)]
final class AssistantThreadStartedEventTest extends TestCase
{
  #[Test]
  public function testExposesPayloadAndStampsOccurredAt(): void
  {
    $event = new AssistantThreadStartedEvent(
      organizationId: 'org-1',
      threadId: 'thread-2',
      memberId: 'member-3',
    );

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('thread-2', $event->threadId);
    self::assertSame('member-3', $event->memberId);
    self::assertNotSame('', $event->occurredAt->format('c'));
  }
}
