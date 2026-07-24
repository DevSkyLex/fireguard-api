<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Contract\Link;

use DateTimeImmutable;
use Messaging\Application\Contract\Link\{MessagingLinkPage, MessagingLinkView};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingLinkPage.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingLinkPage::class)]
final class MessagingLinkPageTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsItemsAndPaginationMetadata(): void
  {
    $link = new MessagingLinkView('link-1', 'msg-1', 'conv-1', 'https://example.com', null, new DateTimeImmutable());

    $page = new MessagingLinkPage(items: [$link], page: 1, itemsPerPage: 15, total: 1);

    self::assertSame([$link], $page->items);
    self::assertSame(1, $page->page);
    self::assertSame(15, $page->itemsPerPage);
    self::assertSame(1, $page->total);
  }
}
