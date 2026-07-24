<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Link;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\MessagingLinkView;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingLinkView.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingLinkView::class)]
final class MessagingLinkViewTest extends TestCase
{
  #[Test]
  public function itRoundTripsEveryProperty(): void
  {
    $createdAt = new DateTimeImmutable('2026-05-01T06:00:00+00:00');

    $view = new MessagingLinkView('link-1', 'msg-1', 'conv-1', 'https://example.com', null, $createdAt);

    self::assertSame('link-1', $view->id);
    self::assertSame('msg-1', $view->messageId);
    self::assertSame('conv-1', $view->conversationId);
    self::assertSame('https://example.com', $view->url);
    self::assertNull($view->label);
    self::assertSame($createdAt, $view->createdAt);
  }
}
