<?php

declare(strict_types=1);

namespace Tests\Unit\Calendar\Domain\Exception;

use Calendar\Domain\Exception\CalendarEventValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test CalendarEventValidationException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CalendarEventValidationException::class)]
final class CalendarEventValidationExceptionTest extends TestCase
{
  #[Test]
  public function itBuildsEndBeforeStartMessage(): void
  {
    $exception = CalendarEventValidationException::endBeforeStart();

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame(
      'The event\'s end date/time cannot be before its start date/time.',
      $exception->getMessage(),
    );
  }
}
