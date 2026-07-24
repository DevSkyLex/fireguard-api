<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Query\Event\GetCalendarEvent;

use Calendar\Application\UseCase\Query\Event\GetCalendarEvent\GetCalendarEventQuery;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetCalendarEventQuery.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetCalendarEventQuery::class)]
final class GetCalendarEventQueryTest extends TestCase
{
  #[Test]
  public function itExposesConstructorArguments(): void
  {
    $query = new GetCalendarEventQuery(
      userId: 'user-1',
      organizationId: 'org-1',
      eventId: 'event-1',
    );

    self::assertSame('user-1', $query->userId);
    self::assertSame('org-1', $query->organizationId);
    self::assertSame('event-1', $query->eventId);
  }
}
