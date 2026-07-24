<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Channel;

use DateTimeImmutable;
use Messaging\Application\Contract\Channel\{ChannelPage, ChannelView};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChannelPage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChannelPage::class)]
final class ChannelPageTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsItemsAndPaginationMetadata(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $item = new ChannelView('conv-1', 'org-1', 'General', null, null, 0, false, null, 0, $now, $now);

    $page = new ChannelPage(items: [$item], page: 1, itemsPerPage: 30, total: 1);

    self::assertSame([$item], $page->items);
    self::assertSame(1, $page->page);
    self::assertSame(30, $page->itemsPerPage);
    self::assertSame(1, $page->total);
  }
}
