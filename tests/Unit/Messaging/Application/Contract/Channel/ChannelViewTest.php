<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Channel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\ChannelView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChannelView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChannelView::class)]
final class ChannelViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $lastMessageAt = new DateTimeImmutable('2026-02-10T09:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T09:00:00+00:00');

    $view = new ChannelView(
      id: 'conv-1',
      organizationId: 'org-1',
      name: 'General',
      teamId: 'team-1',
      createdByMemberId: 'member-1',
      participantCount: 5,
      isArchived: false,
      lastMessageAt: $lastMessageAt,
      messagesCount: 12,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      parentChannelId: 'parent-1',
    );

    self::assertSame('conv-1', $view->id);
    self::assertSame('org-1', $view->organizationId);
    self::assertSame('General', $view->name);
    self::assertSame('team-1', $view->teamId);
    self::assertSame('member-1', $view->createdByMemberId);
    self::assertSame(5, $view->participantCount);
    self::assertFalse($view->isArchived);
    self::assertSame($lastMessageAt, $view->lastMessageAt);
    self::assertSame(12, $view->messagesCount);
    self::assertSame($createdAt, $view->createdAt);
    self::assertSame($updatedAt, $view->updatedAt);
    self::assertSame('parent-1', $view->parentChannelId);
  }

  #[Test]
  public function itDefaultsTheParentChannelToNull(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $view = new ChannelView('conv-1', 'org-1', 'General', null, null, 0, true, null, 0, $now, $now);

    self::assertNull($view->parentChannelId);
    self::assertNull($view->teamId);
    self::assertTrue($view->isArchived);
  }
}
