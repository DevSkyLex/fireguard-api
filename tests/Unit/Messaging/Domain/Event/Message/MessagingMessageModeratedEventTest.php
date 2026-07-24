<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Event\Message;

use Messaging\Domain\Event\Message\MessagingMessageModeratedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Test MessagingMessageModeratedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingMessageModeratedEvent::class)]
final class MessagingMessageModeratedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndStampsOccurredAt(): void
  {
    $event = new MessagingMessageModeratedEvent('org-1', 'conv-1', 'msg-1', 'author-1', 'user-1');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('conv-1', $event->conversationId);
    self::assertSame('msg-1', $event->messageId);
    self::assertSame('author-1', $event->authorMemberId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format(DATE_ATOM));
  }

  #[Test]
  public function itDefaultsTheActorToNull(): void
  {
    $event = new MessagingMessageModeratedEvent('org-1', 'conv-1', 'msg-1', 'author-1');

    self::assertNull($event->actorUserId);
  }
}
