<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\UseCase\Query\Conversation\ListConversations;

use DateTimeImmutable;
use Messaging\Application\Contract\Conversation\{ConversationPage, ConversationView};
use Messaging\Application\UseCase\Query\Conversation\ListConversations\ListConversationsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListConversationsResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListConversationsResult::class)]
final class ListConversationsResultTest extends TestCase
{
  #[Test]
  public function testItCarriesThePageUnreadCountsAndFavorites(): void
  {
    $now = new DateTimeImmutable('2026-01-01T09:00:00+00:00');
    $page = new ConversationPage(
      items: [new ConversationView('conv-1', 'org-1', 'facility', 'sub-1', 'subject', null, 0, false, $now, $now)],
      page: 1,
      itemsPerPage: 30,
      total: 1,
    );

    $result = new ListConversationsResult($page, ['conv-1' => 4], ['conv-1']);

    self::assertSame($page, $result->page);
    self::assertSame(['conv-1' => 4], $result->unreadCounts);
    self::assertSame(['conv-1'], $result->favoriteConversationIds);
  }

  #[Test]
  public function testFavoriteConversationIdsDefaultToAnEmptyList(): void
  {
    $page = new ConversationPage(items: [], page: 1, itemsPerPage: 30, total: 0);

    self::assertSame([], new ListConversationsResult($page, [])->favoriteConversationIds);
  }
}
