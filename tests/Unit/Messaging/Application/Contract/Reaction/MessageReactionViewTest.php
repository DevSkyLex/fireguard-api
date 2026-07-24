<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Reaction;

use DateTimeImmutable;
use Messaging\Application\Contract\Reaction\MessageReactionView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessageReactionView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageReactionView::class)]
final class MessageReactionViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $createdAt = new DateTimeImmutable('2026-03-01T08:00:00+00:00');

    $view = new MessageReactionView('msg-1', 'member-1', "\u{1F44D}", $createdAt);

    self::assertSame('msg-1', $view->messageId);
    self::assertSame('member-1', $view->memberId);
    self::assertSame("\u{1F44D}", $view->emoji);
    self::assertSame($createdAt, $view->createdAt);
  }
}
