<?php

declare(strict_types=1);

namespace Tests\Unit\Notification\Application\Service;

use DateTimeImmutable;
use Notification\Application\Contract\Inbox\InboxItem;
use Notification\Application\Service\InboxAggregationResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InboxAggregationResultTest.
 *
 * `hasMore` is what the client turns into a "load more" affordance, so it has
 * to stay an independent flag rather than something inferred from the item
 * count — an empty page can still legitimately advertise more.
 *
 * @category Service Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InboxAggregationResult::class)]
final class InboxAggregationResultTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testConstructorKeepsTheMergedItemsInTheGivenOrder(): void
  {
    $first = $this->item('a');
    $second = $this->item('b');

    $aggregation = new InboxAggregationResult([$first, $second], true);

    self::assertSame([$first, $second], $aggregation->items);
    self::assertTrue($aggregation->hasMore);
  }

  #[Test]
  public function testHasMoreStaysIndependentFromTheItemCount(): void
  {
    $aggregation = new InboxAggregationResult([], false);

    self::assertSame([], $aggregation->items);
    self::assertFalse($aggregation->hasMore);
  }

  private function item(string $id): InboxItem
  {
    return new InboxItem(
      sourceKey: 'notification',
      id: $id,
      kind: 'notification',
      title: 'Inspection due',
      snippet: null,
      occurredAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
      isRead: false,
      organizationId: null,
      targetType: 'notification',
      targetId: $id,
    );
  }
  // #endregion
}
