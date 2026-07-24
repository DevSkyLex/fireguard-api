<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Message;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\MessageView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessageView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageView::class)]
final class MessageViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $editedAt = new DateTimeImmutable('2026-02-01T09:00:00+00:00');
    $deletedAt = new DateTimeImmutable('2026-02-02T09:00:00+00:00');
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T09:00:00+00:00');
    $pinnedAt = new DateTimeImmutable('2026-01-03T09:00:00+00:00');

    $view = new MessageView(
      id: 'msg-1',
      conversationId: 'conv-1',
      organizationId: 'org-1',
      authorMemberId: 'member-1',
      body: 'Hello team',
      mentions: ['member-2'],
      editedAt: $editedAt,
      deletedAt: $deletedAt,
      deletedByMemberId: 'moderator-1',
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      pinnedAt: $pinnedAt,
      pinnedByMemberId: 'member-3',
      parentMessageId: 'root-1',
      replyCount: 4,
      references: [['type' => 'facility', 'id' => 'f-1', 'label' => 'HQ', 'code' => 'F-01']],
    );

    self::assertSame('msg-1', $view->id);
    self::assertSame('conv-1', $view->conversationId);
    self::assertSame('org-1', $view->organizationId);
    self::assertSame('member-1', $view->authorMemberId);
    self::assertSame('Hello team', $view->body);
    self::assertSame(['member-2'], $view->mentions);
    self::assertSame($editedAt, $view->editedAt);
    self::assertSame($deletedAt, $view->deletedAt);
    self::assertSame('moderator-1', $view->deletedByMemberId);
    self::assertSame($createdAt, $view->createdAt);
    self::assertSame($updatedAt, $view->updatedAt);
    self::assertSame($pinnedAt, $view->pinnedAt);
    self::assertSame('member-3', $view->pinnedByMemberId);
    self::assertSame('root-1', $view->parentMessageId);
    self::assertSame(4, $view->replyCount);
    self::assertSame([['type' => 'facility', 'id' => 'f-1', 'label' => 'HQ', 'code' => 'F-01']], $view->references);
  }

  #[Test]
  public function itAppliesDefaultsForTheOptionalTail(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T09:00:00+00:00');

    $view = new MessageView(
      id: 'msg-1',
      conversationId: 'conv-1',
      organizationId: 'org-1',
      authorMemberId: 'member-1',
      body: 'Hi',
      mentions: [],
      editedAt: null,
      deletedAt: null,
      deletedByMemberId: null,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
    );

    self::assertNull($view->pinnedAt);
    self::assertNull($view->pinnedByMemberId);
    self::assertNull($view->parentMessageId);
    self::assertSame(0, $view->replyCount);
    self::assertSame([], $view->references);
  }
}
