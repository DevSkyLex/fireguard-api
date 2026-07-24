<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Domain\Exception;

use Intervention\Domain\Exception\InterventionResourceNotFoundException;
use Intervention\Domain\ValueObject\InterventionResourceType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test InterventionResourceNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InterventionResourceNotFoundException::class)]
final class InterventionResourceNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdUppercasesTheResourceTypeInTheMessage(): void
  {
    $exception = InterventionResourceNotFoundException::withId(InterventionResourceType::EQUIPMENT, 'res-9');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Equipment resource with ID "res-9" not found.', $exception->getMessage());
  }

  #[Test]
  public function testWithIdSupportsEveryResourceType(): void
  {
    $facility = InterventionResourceNotFoundException::withId(InterventionResourceType::FACILITY, 'a');
    $inspection = InterventionResourceNotFoundException::withId(InterventionResourceType::INSPECTION, 'b');

    self::assertSame('Facility resource with ID "a" not found.', $facility->getMessage());
    self::assertSame('Inspection resource with ID "b" not found.', $inspection->getMessage());
  }
}
