<?php

declare(strict_types=1);

namespace Tests\Unit\Maintenance\Application\Contract\Compliance;

use Maintenance\Application\Contract\Compliance\MaintenanceCompliancePolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MaintenanceCompliancePolicy.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MaintenanceCompliancePolicy::class)]
final class MaintenanceCompliancePolicyTest extends TestCase
{
  #[Test]
  public function testExposesTheReminderWindow(): void
  {
    $policy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 14);

    self::assertSame(14, $policy->reminderWindowDays);
  }

  #[Test]
  public function testPeriodicityForReturnsTheTrackedPeriodicity(): void
  {
    $policy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D', 'alarm' => 'P180D'], 14);

    self::assertSame('P90D', $policy->periodicityFor('fire_extinguisher'));
    self::assertSame('P180D', $policy->periodicityFor('alarm'));
  }

  #[Test]
  public function testPeriodicityForReturnsNullForAnUntrackedType(): void
  {
    $policy = new MaintenanceCompliancePolicy(['fire_extinguisher' => 'P90D'], 7);

    self::assertNull($policy->periodicityFor('unknown_type'));
  }
}
