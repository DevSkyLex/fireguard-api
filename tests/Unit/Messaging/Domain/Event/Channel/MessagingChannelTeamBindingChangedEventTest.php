<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Event\Channel;

use Messaging\Domain\Event\Channel\MessagingChannelTeamBindingChangedEvent;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use const DATE_ATOM;

/**
 * Test MessagingChannelTeamBindingChangedEvent.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingChannelTeamBindingChangedEvent::class)]
final class MessagingChannelTeamBindingChangedEventTest extends TestCase
{
  #[Test]
  public function itExposesItsPayloadAndStampsOccurredAt(): void
  {
    $event = new MessagingChannelTeamBindingChangedEvent('org-1', 'conv-1', 'team-1', 'user-1');

    self::assertSame('org-1', $event->organizationId);
    self::assertSame('conv-1', $event->conversationId);
    self::assertSame('team-1', $event->teamId);
    self::assertSame('user-1', $event->actorUserId);
    self::assertNotSame('', $event->occurredAt->format(DATE_ATOM));
  }

  #[Test]
  public function itAcceptsAnUnboundTeamAndDefaultActor(): void
  {
    $event = new MessagingChannelTeamBindingChangedEvent('org-1', 'conv-1', null);

    self::assertNull($event->teamId);
    self::assertNull($event->actorUserId);
  }
}
