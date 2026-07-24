<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent;

use Calendar\Application\UseCase\Command\Event\DeleteCalendarEvent\DeleteCalendarEventResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DeleteCalendarEventResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(DeleteCalendarEventResult::class)]
final class DeleteCalendarEventResultTest extends TestCase
{
  #[Test]
  public function itExposesConstructorArguments(): void
  {
    $result = new DeleteCalendarEventResult(
      eventId: 'event-1',
      organizationId: 'org-1',
    );

    self::assertSame('event-1', $result->eventId);
    self::assertSame('org-1', $result->organizationId);
  }
}
