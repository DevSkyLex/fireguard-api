<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Presentation\Api\Dto\Output\Organization;

use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\ValueObject\{
  OrganizationApprovalSettings,
  OrganizationAssistantSettings,
  OrganizationAutomationSettings,
  OrganizationComplianceSettings,
  OrganizationNotificationSettings,
  OrganizationRegionalSettings,
  OrganizationSettings
};
use Organization\Presentation\Api\Dto\Output\Organization\{
  OrganizationApprovalSettingsOutput,
  OrganizationAssistantSettingsOutput,
  OrganizationAutomationSettingsOutput,
  OrganizationComplianceSettingsOutput,
  OrganizationNotificationSettingsOutput,
  OrganizationRegionalSettingsOutput,
  OrganizationSettingsOutput
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test the organization settings output DTOs.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationSettingsOutput::class)]
#[CoversClass(OrganizationApprovalSettingsOutput::class)]
#[CoversClass(OrganizationAssistantSettingsOutput::class)]
#[CoversClass(OrganizationAutomationSettingsOutput::class)]
#[CoversClass(OrganizationComplianceSettingsOutput::class)]
#[CoversClass(OrganizationNotificationSettingsOutput::class)]
#[CoversClass(OrganizationRegionalSettingsOutput::class)]
final class OrganizationSettingsOutputTest extends TestCase
{
  #[Test]
  public function testApprovalOutputCopiesEveryDomainField(): void
  {
    $output = OrganizationApprovalSettingsOutput::fromDomain(new OrganizationApprovalSettings(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical']],
      allowSelfApproval: true,
      approvalTtlDays: 21,
    ));

    self::assertSame(
      ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical']],
      $output->actionRules,
    );
    self::assertTrue($output->allowSelfApproval);
    self::assertSame(21, $output->approvalTtlDays);
  }

  #[Test]
  public function testAssistantOutputCopiesEveryDomainField(): void
  {
    $output = OrganizationAssistantSettingsOutput::fromDomain(new OrganizationAssistantSettings(
      enabled: true,
      model: 'claude-model',
      temperature: 0.4,
      includeBusinessContext: false,
    ));

    self::assertTrue($output->enabled);
    self::assertSame('claude-model', $output->model);
    self::assertSame(0.4, $output->temperature);
    self::assertFalse($output->includeBusinessContext);
  }

  #[Test]
  public function testAssistantOutputKeepsANullModelOverride(): void
  {
    $output = OrganizationAssistantSettingsOutput::fromDomain(new OrganizationAssistantSettings(model: null));

    self::assertNull($output->model);
  }

  #[Test]
  public function testAutomationOutputCopiesTheDomainFlag(): void
  {
    $output = OrganizationAutomationSettingsOutput::fromDomain(
      new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true),
    );

    self::assertTrue($output->autoCreateInterventionOnCriticalNc);
  }

  #[Test]
  public function testComplianceOutputExposesEffectiveAndCustomizedValues(): void
  {
    $output = OrganizationComplianceSettingsOutput::fromDomain(new OrganizationComplianceSettings(
      nonConformitySlaDays: ['critical' => 2],
      inspectionPeriodicityDefaults: ['fire_extinguisher' => 'P6M'],
      reminderWindowDays: 12,
    ));

    self::assertSame(2, $output->nonConformitySlaDays['critical']);
    self::assertSame(
      OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS['low'],
      $output->nonConformitySlaDays['low'],
    );
    self::assertSame('P6M', $output->inspectionPeriodicityDefaults['fire_extinguisher']);
    self::assertSame(12, $output->reminderWindowDays);
    self::assertSame(['critical'], $output->customizedSlaSeverities);
    self::assertSame(['fire_extinguisher'], $output->customizedPeriodicityTypes);
  }

  #[Test]
  public function testComplianceOutputReportsNoCustomizationForDefaults(): void
  {
    $output = OrganizationComplianceSettingsOutput::fromDomain(new OrganizationComplianceSettings());

    self::assertSame([], $output->customizedSlaSeverities);
    self::assertSame([], $output->customizedPeriodicityTypes);
    self::assertSame(OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS, $output->reminderWindowDays);
  }

  #[Test]
  public function testNotificationOutputCopiesEveryToggle(): void
  {
    $output = OrganizationNotificationSettingsOutput::fromDomain(new OrganizationNotificationSettings(
      emailEnabled: false,
      inAppEnabled: false,
      interventionPublished: false,
      interventionAssigned: false,
      inspectionDue: false,
      nonConformityOpened: false,
      memberInvited: false,
    ));

    self::assertFalse($output->emailEnabled);
    self::assertFalse($output->inAppEnabled);
    self::assertFalse($output->interventionPublished);
    self::assertFalse($output->interventionAssigned);
    self::assertFalse($output->inspectionDue);
    self::assertFalse($output->nonConformityOpened);
    self::assertFalse($output->memberInvited);
  }

  #[Test]
  public function testNotificationOutputDefaultsToEveryToggleEnabled(): void
  {
    $output = OrganizationNotificationSettingsOutput::fromDomain(new OrganizationNotificationSettings());

    self::assertTrue($output->emailEnabled);
    self::assertTrue($output->memberInvited);
  }

  #[Test]
  public function testRegionalOutputCopiesEveryDomainField(): void
  {
    $output = OrganizationRegionalSettingsOutput::fromDomain(new OrganizationRegionalSettings(
      timezone: 'Europe/Paris',
      locale: 'fr-FR',
      dateFormat: 'dd/MM/yyyy',
      firstDayOfWeek: 'monday',
      measurementSystem: 'metric',
    ));

    self::assertSame('Europe/Paris', $output->timezone);
    self::assertSame('fr-FR', $output->locale);
    self::assertSame('dd/MM/yyyy', $output->dateFormat);
    self::assertSame('monday', $output->firstDayOfWeek);
    self::assertSame('metric', $output->measurementSystem);
  }

  #[Test]
  public function testSettingsOutputStartsWithFullyPopulatedSections(): void
  {
    $output = new OrganizationSettingsOutput();

    self::assertInstanceOf(OrganizationNotificationSettingsOutput::class, $output->notifications);
    self::assertInstanceOf(OrganizationRegionalSettingsOutput::class, $output->regional);
    self::assertInstanceOf(OrganizationComplianceSettingsOutput::class, $output->compliance);
    self::assertInstanceOf(OrganizationAutomationSettingsOutput::class, $output->automation);
    self::assertInstanceOf(OrganizationApprovalSettingsOutput::class, $output->approval);
    self::assertInstanceOf(OrganizationAssistantSettingsOutput::class, $output->assistant);
  }

  #[Test]
  public function testSettingsOutputFromDomainMapsEverySection(): void
  {
    $output = OrganizationSettingsOutput::fromDomain(new OrganizationSettings(
      notifications: new OrganizationNotificationSettings(emailEnabled: false),
      regional: new OrganizationRegionalSettings(timezone: 'Europe/Paris'),
      compliance: new OrganizationComplianceSettings(reminderWindowDays: 7),
      automation: new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true),
      approval: new OrganizationApprovalSettings(allowSelfApproval: true),
      assistant: new OrganizationAssistantSettings(enabled: true, model: 'claude-model'),
    ));

    self::assertFalse($output->notifications->emailEnabled);
    self::assertSame('Europe/Paris', $output->regional->timezone);
    self::assertSame(7, $output->compliance->reminderWindowDays);
    self::assertTrue($output->automation->autoCreateInterventionOnCriticalNc);
    self::assertTrue($output->approval->allowSelfApproval);
    self::assertSame('claude-model', $output->assistant->model);
  }
}
