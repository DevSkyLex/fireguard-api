<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\{
  OrganizationApprovalSettings,
  OrganizationAssistantSettings,
  OrganizationAutomationSettings,
  OrganizationComplianceSettings,
  OrganizationNotificationSettings,
  OrganizationRegionalSettings,
  OrganizationSettings
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

#[CoversClass(OrganizationSettings::class)]
#[CoversClass(OrganizationNotificationSettings::class)]
#[CoversClass(OrganizationRegionalSettings::class)]
#[CoversClass(OrganizationApprovalSettings::class)]
#[CoversClass(OrganizationAssistantSettings::class)]
final class OrganizationSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultAppliesSensibleDefaults(): void
  {
    $settings = OrganizationSettings::default();

    self::assertTrue($settings->notifications->emailEnabled);
    self::assertTrue($settings->notifications->inAppEnabled);
    self::assertTrue($settings->notifications->memberInvited);
    self::assertSame('UTC', $settings->regional->timezone);
    self::assertSame('en-US', $settings->regional->locale);
    self::assertSame('yyyy-MM-dd', $settings->regional->dateFormat);
    self::assertSame('monday', $settings->regional->firstDayOfWeek);
    self::assertSame('metric', $settings->regional->measurementSystem);
  }

  #[Test]
  public function testFromEmptyArrayFallsBackToDefaults(): void
  {
    $settings = OrganizationSettings::fromArray([]);

    self::assertEquals(OrganizationSettings::default(), $settings);
  }

  #[Test]
  public function testToArrayAndFromArrayRoundTrip(): void
  {
    $settings = new OrganizationSettings(
      notifications: new OrganizationNotificationSettings(
        emailEnabled: false,
        inAppEnabled: true,
        interventionPublished: false,
        interventionAssigned: true,
        inspectionDue: false,
        nonConformityOpened: true,
        memberInvited: false,
      ),
      regional: new OrganizationRegionalSettings(
        timezone: 'Europe/Paris',
        locale: 'fr-FR',
        dateFormat: 'dd/MM/yyyy',
        firstDayOfWeek: 'sunday',
        measurementSystem: 'imperial',
      ),
    );

    $restored = OrganizationSettings::fromArray($settings->toArray());

    self::assertEquals($settings, $restored);
    self::assertFalse($restored->notifications->emailEnabled);
    self::assertSame('Europe/Paris', $restored->regional->timezone);
    self::assertSame('fr-FR', $restored->regional->locale);
  }

  #[Test]
  public function testWithNotificationsReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withNotifications(new OrganizationNotificationSettings(emailEnabled: false));

    self::assertNotSame($settings, $updated);
    self::assertTrue($settings->notifications->emailEnabled, 'original is unchanged');
    self::assertFalse($updated->notifications->emailEnabled);
    self::assertEquals($settings->regional, $updated->regional, 'regional section is preserved');
  }

  #[Test]
  public function testWithRegionalReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withRegional(new OrganizationRegionalSettings(timezone: 'Europe/Paris'));

    self::assertNotSame($settings, $updated);
    self::assertSame('UTC', $settings->regional->timezone, 'original is unchanged');
    self::assertSame('Europe/Paris', $updated->regional->timezone);
    self::assertEquals($settings->notifications, $updated->notifications, 'notifications section is preserved');
  }

  #[Test]
  public function testWithComplianceReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withCompliance(new OrganizationComplianceSettings(reminderWindowDays: 45));

    self::assertNotSame($settings, $updated);
    self::assertSame(30, $settings->compliance->reminderWindowDays, 'original is unchanged');
    self::assertSame(45, $updated->compliance->reminderWindowDays);
    self::assertEquals($settings->notifications, $updated->notifications, 'notifications section is preserved');
  }

  #[Test]
  public function testWithAutomationReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withAutomation(new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true));

    self::assertNotSame($settings, $updated);
    self::assertFalse($settings->automation->autoCreateInterventionOnCriticalNc, 'original is unchanged');
    self::assertTrue($updated->automation->autoCreateInterventionOnCriticalNc);
    self::assertEquals($settings->regional, $updated->regional, 'regional section is preserved');
  }

  #[Test]
  public function testToArrayCarriesSchemaVersion(): void
  {
    $data = OrganizationSettings::default()->toArray();

    self::assertSame(OrganizationSettings::SCHEMA_VERSION, $data['version']);
    self::assertArrayHasKey('compliance', $data);
    self::assertArrayHasKey('automation', $data);
  }

  #[Test]
  public function testRegionalRejectsUnknownTimezone(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegionalSettings(timezone: 'Mars/Olympus');
  }

  #[Test]
  public function testRegionalRejectsUnsupportedLocale(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegionalSettings(locale: 'xx-XX');
  }

  #[Test]
  public function testRegionalRejectsUnsupportedMeasurementSystem(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationRegionalSettings(measurementSystem: 'furlongs');
  }

  #[Test]
  public function testWithApprovalReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withApproval(new OrganizationApprovalSettings(allowSelfApproval: true, approvalTtlDays: 30));

    self::assertNotSame($settings, $updated);
    self::assertFalse($settings->approval->allowSelfApproval, 'original is unchanged');
    self::assertSame(14, $settings->approval->approvalTtlDays, 'original is unchanged');
    self::assertTrue($updated->approval->allowSelfApproval);
    self::assertSame(30, $updated->approval->approvalTtlDays);
    self::assertEquals($settings->assistant, $updated->assistant, 'assistant section is preserved');
    self::assertEquals($settings->notifications, $updated->notifications, 'notifications section is preserved');
  }

  #[Test]
  public function testWithAssistantReturnsNewImmutableInstance(): void
  {
    $settings = OrganizationSettings::default();
    $updated = $settings->withAssistant(new OrganizationAssistantSettings(enabled: true, temperature: 0.9));

    self::assertNotSame($settings, $updated);
    self::assertFalse($settings->assistant->enabled, 'original is unchanged');
    self::assertTrue($updated->assistant->enabled);
    self::assertSame(0.9, $updated->assistant->temperature);
    self::assertEquals($settings->approval, $updated->approval, 'approval section is preserved');
    self::assertEquals($settings->regional, $updated->regional, 'regional section is preserved');
  }

  #[Test]
  public function testFromArrayHydratesApprovalAndAssistantSections(): void
  {
    $settings = new OrganizationSettings(
      approval: new OrganizationApprovalSettings(allowSelfApproval: true, approvalTtlDays: 21),
      assistant: new OrganizationAssistantSettings(
        enabled: true,
        model: 'llama3',
        temperature: 0.7,
        includeBusinessContext: false,
      ),
    );

    $restored = OrganizationSettings::fromArray($settings->toArray());

    self::assertEquals($settings, $restored);
    self::assertTrue($restored->approval->allowSelfApproval);
    self::assertSame(21, $restored->approval->approvalTtlDays);
    self::assertTrue($restored->assistant->enabled);
    self::assertSame('llama3', $restored->assistant->model);
    self::assertSame(0.7, $restored->assistant->temperature);
    self::assertFalse($restored->assistant->includeBusinessContext);
  }

  #[Test]
  public function testFromArrayToleratesNonArraySubSections(): void
  {
    $settings = OrganizationSettings::fromArray([
      'notifications' => 'not-an-array',
      'regional' => 42,
      'compliance' => false,
      'automation' => 'nope',
      'approval' => 3.14,
      'assistant' => 'still-not-an-array',
    ]);

    self::assertEquals(OrganizationSettings::default(), $settings);
  }
}
