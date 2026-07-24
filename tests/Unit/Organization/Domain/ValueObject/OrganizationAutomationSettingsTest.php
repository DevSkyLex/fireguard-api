<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationAutomationSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationAutomationSettings.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAutomationSettings::class)]
final class OrganizationAutomationSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsToDisabled(): void
  {
    $settings = new OrganizationAutomationSettings();

    self::assertFalse($settings->autoCreateInterventionOnCriticalNc);
  }

  #[Test]
  public function testToArrayExposesSnakeCaseKey(): void
  {
    $settings = new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true);

    self::assertSame(
      ['auto_create_intervention_on_critical_nc' => true],
      $settings->toArray(),
    );
  }

  #[Test]
  public function testFromArrayFallsBackToDisabled(): void
  {
    $settings = OrganizationAutomationSettings::fromArray([]);

    self::assertFalse($settings->autoCreateInterventionOnCriticalNc);
  }

  #[Test]
  public function testToArrayFromArrayRoundTrip(): void
  {
    $original = new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true);

    $restored = OrganizationAutomationSettings::fromArray($original->toArray());

    self::assertEquals($original, $restored);
  }
}
