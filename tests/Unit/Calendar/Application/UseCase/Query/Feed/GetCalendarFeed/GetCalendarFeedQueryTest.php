<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Feed\GetCalendarFeed;

use Calendar\Application\UseCase\Query\Feed\GetCalendarFeed\GetCalendarFeedQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCalendarFeedQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarFeedQuery::class)]
final class GetCalendarFeedQueryTest extends TestCase
{
  #[Test]
  public function itExposesConstructorArguments(): void
  {
    $query = new GetCalendarFeedQuery(
      userId: 'user-1',
      organizationId: 'org-1',
      from: '2026-08-01T00:00:00+02:00',
      to: '2026-08-31T23:59:59+02:00',
    );

    self::assertSame('user-1', $query->userId);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('2026-08-01T00:00:00+02:00', $query->from);
    self::assertSame('2026-08-31T23:59:59+02:00', $query->to);
  }
}
