<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Conversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\ConversationView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConversationView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConversationView::class)]
final class ConversationViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $lastMessageAt = new DateTimeImmutable('2026-02-10T09:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T09:00:00+00:00');

    $view = new ConversationView(
      id: 'conv-1',
      organizationId: 'org-1',
      subjectType: 'facility',
      subjectId: 'sub-1',
      visibility: 'subject',
      lastMessageAt: $lastMessageAt,
      messagesCount: 7,
      isArchived: false,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      name: 'Ops',
      teamId: 'team-1',
      createdByMemberId: 'member-1',
      parentConversationId: 'parent-1',
    );

    self::assertSame('conv-1', $view->id);
    self::assertSame('org-1', $view->organizationId);
    self::assertSame('facility', $view->subjectType);
    self::assertSame('sub-1', $view->subjectId);
    self::assertSame('subject', $view->visibility);
    self::assertSame($lastMessageAt, $view->lastMessageAt);
    self::assertSame(7, $view->messagesCount);
    self::assertFalse($view->isArchived);
    self::assertSame($createdAt, $view->createdAt);
    self::assertSame($updatedAt, $view->updatedAt);
    self::assertSame('Ops', $view->name);
    self::assertSame('team-1', $view->teamId);
    self::assertSame('member-1', $view->createdByMemberId);
    self::assertSame('parent-1', $view->parentConversationId);
  }

  #[Test]
  public function itDefaultsTheChannelSpecificTailToNull(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');

    $view = new ConversationView('conv-1', 'org-1', 'facility', 'sub-1', 'subject', null, 0, false, $now, $now);

    self::assertNull($view->name);
    self::assertNull($view->teamId);
    self::assertNull($view->createdByMemberId);
    self::assertNull($view->parentConversationId);
  }
}
