<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Exception;

use Calendar\Domain\Exception\CalendarEventNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test CalendarEventNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventNotFoundException::class)]
final class CalendarEventNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsMessageWithId(): void
  {
    $exception = CalendarEventNotFoundException::withId('event-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Calendar event with ID "event-1" not found.', $exception->getMessage());
  }
}
