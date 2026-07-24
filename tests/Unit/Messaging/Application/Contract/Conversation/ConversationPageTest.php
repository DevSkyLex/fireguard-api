<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Conversation;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConversationPage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConversationPage::class)]
final class ConversationPageTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsItemsAndPaginationMetadata(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $item = new ConversationView('conv-1', 'org-1', 'facility', 'sub-1', 'subject', null, 0, false, $now, $now);

    $page = new ConversationPage(items: [$item], page: 3, itemsPerPage: 20, total: 60);

    self::assertSame([$item], $page->items);
    self::assertSame(3, $page->page);
    self::assertSame(20, $page->itemsPerPage);
    self::assertSame(60, $page->total);
  }
}
