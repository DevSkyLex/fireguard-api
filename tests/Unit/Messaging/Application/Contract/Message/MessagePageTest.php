<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Message;

use DateTimeImmutable;
use Messaging\Application\Contract\Message\{MessagePage, MessageView};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagePage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagePage::class)]
final class MessagePageTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsItemsAndPaginationMetadata(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $item = new MessageView('msg-1', 'conv-1', 'org-1', 'member-1', 'Hi', [], null, null, null, $now, $now);

    $page = new MessagePage(items: [$item], page: 2, itemsPerPage: 25, total: 51);

    self::assertSame([$item], $page->items);
    self::assertSame(2, $page->page);
    self::assertSame(25, $page->itemsPerPage);
    self::assertSame(51, $page->total);
  }

  #[Test]
  public function itAcceptsAnEmptyPage(): void
  {
    $page = new MessagePage(items: [], page: 1, itemsPerPage: 25, total: 0);

    self::assertSame([], $page->items);
    self::assertSame(0, $page->total);
  }
}
