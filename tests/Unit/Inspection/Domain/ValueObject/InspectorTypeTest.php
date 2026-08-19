<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\InspectorType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test InspectorType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(InspectorType::class)]
final class InspectorTypeTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['user', 'external'], InspectorType::values());
  }
}
