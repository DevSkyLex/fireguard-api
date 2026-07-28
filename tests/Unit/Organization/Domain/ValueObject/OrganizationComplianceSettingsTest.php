<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\{
  OrganizationAutomationSettings,
  OrganizationComplianceSettings
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(OrganizationComplianceSettings::class)]
#[CoversClass(OrganizationAutomationSettings::class)]
#[CoversClass(OrganizationComplianceDefaults::class)]
final class OrganizationComplianceSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsResolveAgainstCatalog(): void
  {
    $compliance = new OrganizationComplianceSettings();

    self::assertSame([], $compliance->nonConformitySlaDays);
    self::assertSame([], $compliance->inspectionPeriodicityDefaults);
    self::assertSame(OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS, $compliance->reminderWindowDays);
    self::assertSame(OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS, $compliance->effectiveNonConformitySlaDays());
    self::assertSame(1, $compliance->slaDaysFor('critical'));
    self::assertSame('P1Y', $compliance->periodicityFor('fire_extinguisher'));
    self::assertSame('P6M', $compliance->periodicityFor('fire_door'));
    self::assertNull($compliance->periodicityFor('other'), 'untracked types have no default periodicity');
  }

  #[Test]
  public function testCustomizationsOverlayCatalogDefaults(): void
  {
    $compliance = new OrganizationComplianceSettings(
      nonConformitySlaDays: ['critical' => 2],
      inspectionPeriodicityDefaults: ['fire_extinguisher' => 'P6M'],
    );

    $effectiveSla = $compliance->effectiveNonConformitySlaDays();
    self::assertSame(2, $effectiveSla['critical']);
    self::assertSame(90, $effectiveSla['low'], 'non-customized severities keep the catalog default');

    self::assertSame('P6M', $compliance->periodicityFor('fire_extinguisher'));
    self::assertSame('P1Y', $compliance->periodicityFor('smoke_detector'));
    self::assertSame(['critical'], $compliance->customizedSeverities());
    self::assertSame(['fire_extinguisher'], $compliance->customizedEquipmentTypes());
  }

  #[Test]
  public function testToArrayPersistsOnlyCustomizations(): void
  {
    $compliance = new OrganizationComplianceSettings(
      nonConformitySlaDays: ['high' => 3],
      reminderWindowDays: 45,
    );

    $data = $compliance->toArray();

    self::assertSame(['high' => 3], $data['non_conformity_sla_days']);
    self::assertSame([], $data['inspection_periodicity_defaults']);
    self::assertSame(45, $data['reminder_window_days']);
    self::assertEquals($compliance, OrganizationComplianceSettings::fromArray($data));
  }

  #[Test]
  public function testMergedWithAddsAndRemovesCustomizations(): void
  {
    $compliance = new OrganizationComplianceSettings(
      nonConformitySlaDays: ['critical' => 2, 'high' => 3],
      inspectionPeriodicityDefaults: ['camera' => 'P2Y'],
    );

    $merged = $compliance->mergedWith([
      'non_conformity_sla_days' => ['high' => null, 'medium' => 15],
      'inspection_periodicity_defaults' => ['camera' => null],
      'reminder_window_days' => 60,
    ]);

    self::assertSame(['critical' => 2, 'medium' => 15], $merged->nonConformitySlaDays);
    self::assertSame([], $merged->inspectionPeriodicityDefaults, 'null entry reverts to the catalog default');
    self::assertSame('P1Y', $merged->periodicityFor('camera'));
    self::assertSame(60, $merged->reminderWindowDays);
    self::assertSame(['critical' => 2, 'high' => 3], $compliance->nonConformitySlaDays, 'original is unchanged');
  }

  #[Test]
  public function testMergedWithLeavesUnprovidedSectionsUnchanged(): void
  {
    $compliance = new OrganizationComplianceSettings(
      nonConformitySlaDays: ['critical' => 2],
      reminderWindowDays: 45,
    );

    $merged = $compliance->mergedWith(['reminder_window_days' => null]);

    self::assertEquals($compliance, $merged);
  }

  #[Test]
  public function testRejectsUnknownSeverity(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(nonConformitySlaDays: ['catastrophic' => 1]);
  }

  #[Test]
  public function testRejectsSlaDaysOutOfBounds(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(nonConformitySlaDays: ['low' => 400]);
  }

  #[Test]
  public function testRejectsMalformedPeriodicity(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: ['camera' => 'every year']);
  }

  #[Test]
  public function testRejectsPeriodicityShorterThanOneMonth(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: ['camera' => 'P1D']);
  }

  #[Test]
  public function testRejectsPeriodicityLongerThanTenYears(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: ['camera' => 'P20Y']);
  }

  #[Test]
  public function testRejectsAnEmptyEquipmentTypeKey(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Equipment type keys must be non-empty strings.');

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: ['' => 'P1Y']);
  }

  #[Test]
  public function testRejectsANonStringEquipmentTypeKey(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Equipment type keys must be non-empty strings.');

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: [7 => 'P1Y']);
  }

  #[Test]
  public function testRejectsANonStringPeriodicity(): void
  {
    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Inspection periodicity for "fire_extinguisher" must be an ISO-8601 duration string.');

    new OrganizationComplianceSettings(inspectionPeriodicityDefaults: ['fire_extinguisher' => 12]);
  }

  #[Test]
  public function testRejectsReminderWindowOutOfBounds(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationComplianceSettings(reminderWindowDays: 0);
  }

  #[Test]
  public function testAutomationDefaultsToEveryRuleOff(): void
  {
    $automation = new OrganizationAutomationSettings();

    self::assertFalse($automation->autoCreateInterventionOnCriticalNc);
    self::assertEquals($automation, OrganizationAutomationSettings::fromArray([]));
  }

  #[Test]
  public function testAutomationRoundTrip(): void
  {
    $automation = new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true);

    $restored = OrganizationAutomationSettings::fromArray($automation->toArray());

    self::assertTrue($restored->autoCreateInterventionOnCriticalNc);
    self::assertEquals($automation, $restored);
  }
}
