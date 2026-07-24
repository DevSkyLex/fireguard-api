<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Event\Channel;

use Messaging\Domain\Event\Channel\MessagingChannelParentChangedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Test MessagingChannelParentChangedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingChannelParentChangedEvent::class)]
final class MessagingChannelParentChangedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndStampsOccurredAt(): void
  {
    $event = new MessagingChannelParentChangedEvent('org-1', 'conv-1', 'parent-1', 'user-1');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('conv-1', $event->conversationId);
    self::assertSame('parent-1', $event->parentConversationId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format(DATE_ATOM));
  }

  #[Test]
  public function itAcceptsADetachedParentAndDefaultActor(): void
  {
    $event = new MessagingChannelParentChangedEvent('org-1', 'conv-1', null);

    self::assertNull($event->parentConversationId);
    self::assertNull($event->actorUserId);
  }
}
