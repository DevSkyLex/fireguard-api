<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Event\Channel;

use Messaging\Domain\Event\Channel\MessagingChannelCreatedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Test MessagingChannelCreatedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingChannelCreatedEvent::class)]
final class MessagingChannelCreatedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndStampsOccurredAt(): void
  {
    $event = new MessagingChannelCreatedEvent('org-1', 'conv-1', 'General', 'member-1', 'user-1');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('conv-1', $event->conversationId);
    self::assertSame('General', $event->name);
    self::assertSame('member-1', $event->createdByMemberId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format(DATE_ATOM));
  }

  #[Test]
  public function itDefaultsTheActorToNull(): void
  {
    $event = new MessagingChannelCreatedEvent('org-1', 'conv-1', 'General', 'member-1');

    self::assertNull($event->actorUserId);
  }
}
