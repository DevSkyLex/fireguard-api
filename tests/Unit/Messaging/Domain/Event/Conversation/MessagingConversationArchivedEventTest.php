<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Event\Conversation;

use Messaging\Domain\Event\Conversation\MessagingConversationArchivedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Test MessagingConversationArchivedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingConversationArchivedEvent::class)]
final class MessagingConversationArchivedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndStampsOccurredAt(): void
  {
    $event = new MessagingConversationArchivedEvent('org-1', 'conv-1', 'facility', 'sub-1', 'user-1');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('conv-1', $event->conversationId);
    self::assertSame('facility', $event->subjectType);
    self::assertSame('sub-1', $event->subjectId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format(DATE_ATOM));
  }

  #[Test]
  public function itAcceptsANullSubjectAndDefaultActor(): void
  {
    $event = new MessagingConversationArchivedEvent('org-1', 'conv-1', 'channel', null);

    self::assertNull($event->subjectId);
    self::assertNull($event->actorUserId);
  }
}
