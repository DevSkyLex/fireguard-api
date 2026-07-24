<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Domain\ValueObject;

use Equipment\Domain\ValueObject\MaintenanceLogSource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceLogSourceTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceLogSource::class)]
final class MaintenanceLogSourceTest extends TestCase
{
  #[Test]
  public function itExposesBackingValues(): void
  {
    self::assertSame('status_transition', MaintenanceLogSource::STATUS_TRANSITION->value);
    self::assertSame('intervention', MaintenanceLogSource::INTERVENTION->value);
  }

  #[Test]
  public function itBuildsFromValue(): void
  {
    self::assertSame(MaintenanceLogSource::INTERVENTION, MaintenanceLogSource::from('intervention'));
  }
}
