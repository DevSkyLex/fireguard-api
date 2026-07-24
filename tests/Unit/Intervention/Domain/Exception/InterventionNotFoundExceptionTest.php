<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Exception;

use Intervention\Domain\Exception\InterventionNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test InterventionNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionNotFoundException::class)]
final class InterventionNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsADescriptiveRuntimeException(): void
  {
    $exception = InterventionNotFoundException::withId('intervention-7');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Intervention with ID "intervention-7" not found.', $exception->getMessage());
  }
}
