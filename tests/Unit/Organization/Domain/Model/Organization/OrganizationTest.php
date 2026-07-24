<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Model\Organization;

use DateTimeImmutable;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationApprovalSettings,
  OrganizationAssistantSettings,
  OrganizationAutomationSettings,
  OrganizationComplianceSettings,
  OrganizationCountry,
  OrganizationId,
  OrganizationLegalType,
  OrganizationName,
  OrganizationNotificationSettings,
  OrganizationRegionalSettings,
  OrganizationRegistrationNumber,
  OrganizationSettings,
  OrganizationSlug,
  OrganizationStatus,
  OrganizationVatNumber,
  PlanId
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(Organization::class)]
final class OrganizationTest extends TestCase
{
  private const string ORGANIZATION_ID = '11111111-1111-4111-8111-111111111111';

  private const string PLAN_ID = '22222222-2222-4222-8222-222222222222';

  private const string OWNER_ID = 'owner-user-id';

  private const string CREATOR_ID = 'creator-user-id';

  #[Test]
  public function testCreateAppliesDefaults(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      ownerUserId: self::OWNER_ID,
    );

    self::assertSame(self::ORGANIZATION_ID, (string) $organization->id());
    self::assertSame('Acme Safety', (string) $organization->name());
    self::assertSame('acme-safety', (string) $organization->slug());
    self::assertSame(self::OWNER_ID, $organization->ownerUserId());
    self::assertSame(self::OWNER_ID, $organization->createdByUserId());
    self::assertSame(OrganizationStatus::ACTIVE, $organization->status());
    self::assertTrue($organization->isActive());
    self::assertFalse($organization->isArchived());
    self::assertNull($organization->description());
    self::assertNull($organization->logoUrl());
    self::assertNull($organization->planId());
    self::assertNull($organization->country());
    self::assertNull($organization->legalType());
    self::assertNull($organization->legalName());
    self::assertNull($organization->registrationNumber());
    self::assertNull($organization->vatNumber());
    self::assertEquals($organization->createdAt(), $organization->updatedAt());
    self::assertInstanceOf(OrganizationSettings::class, $organization->settings());
  }

  #[Test]
  public function testCreateHonoursProvidedOptionalArguments(): void
  {
    $settings = OrganizationSettings::default();

    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      ownerUserId: self::OWNER_ID,
      slug: new OrganizationSlug('custom-slug'),
      createdByUserId: self::CREATOR_ID,
      description: 'Fire safety experts',
      logoUrl: 'https://cdn.example.test/logo.png',
      settings: $settings,
      planId: PlanId::fromString(self::PLAN_ID),
    );

    self::assertSame('custom-slug', (string) $organization->slug());
    self::assertSame(self::CREATOR_ID, $organization->createdByUserId());
    self::assertSame('Fire safety experts', $organization->description());
    self::assertSame('https://cdn.example.test/logo.png', $organization->logoUrl());
    self::assertSame($settings, $organization->settings());
    self::assertSame(self::PLAN_ID, (string) $organization->planId());
  }

  #[Test]
  public function testReconstituteAppliesDefaults(): void
  {
    $createdAt = new DateTimeImmutable('2020-01-01T00:00:00+00:00');

    $organization = Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      createdByUserId: self::CREATOR_ID,
      isActive: true,
      createdAt: $createdAt,
    );

    self::assertSame('acme-safety', (string) $organization->slug());
    self::assertSame(self::CREATOR_ID, $organization->ownerUserId());
    self::assertSame(self::CREATOR_ID, $organization->createdByUserId());
    self::assertSame(OrganizationStatus::ACTIVE, $organization->status());
    self::assertSame($createdAt, $organization->createdAt());
    self::assertSame($createdAt, $organization->updatedAt());
  }

  #[Test]
  public function testReconstituteInactiveFallsBackToSuspendedStatus(): void
  {
    $organization = Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      createdByUserId: self::CREATOR_ID,
      isActive: false,
      createdAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
    );

    self::assertSame(OrganizationStatus::SUSPENDED, $organization->status());
    self::assertFalse($organization->isActive());
  }

  #[Test]
  public function testReconstituteHonoursExplicitState(): void
  {
    $createdAt = new DateTimeImmutable('2020-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2021-06-15T12:00:00+00:00');

    $organization = Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      createdByUserId: self::CREATOR_ID,
      isActive: true,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      ownerUserId: self::OWNER_ID,
      slug: new OrganizationSlug('acme-legacy'),
      status: OrganizationStatus::ARCHIVED,
      description: 'Reconstituted',
      logoUrl: 'https://cdn.example.test/legacy.png',
      settings: OrganizationSettings::default(),
      planId: PlanId::fromString(self::PLAN_ID),
      country: new OrganizationCountry('FR'),
      legalType: OrganizationLegalType::LIMITED_LIABILITY_COMPANY,
      legalName: 'Acme Safety SAS',
      registrationNumber: new OrganizationRegistrationNumber('RCS-123456'),
      vatNumber: new OrganizationVatNumber('FR12345678901'),
    );

    self::assertSame(self::OWNER_ID, $organization->ownerUserId());
    self::assertSame('acme-legacy', (string) $organization->slug());
    self::assertSame(OrganizationStatus::ARCHIVED, $organization->status());
    self::assertTrue($organization->isArchived());
    self::assertSame($updatedAt, $organization->updatedAt());
    self::assertSame('Reconstituted', $organization->description());
    self::assertSame('https://cdn.example.test/legacy.png', $organization->logoUrl());
    self::assertSame(self::PLAN_ID, (string) $organization->planId());
    self::assertSame('FR', (string) $organization->country());
    self::assertSame(OrganizationLegalType::LIMITED_LIABILITY_COMPANY, $organization->legalType());
    self::assertSame('Acme Safety SAS', $organization->legalName());
    self::assertSame('RCS-123456', (string) $organization->registrationNumber());
    self::assertSame('FR12345678901', (string) $organization->vatNumber());
  }

  #[Test]
  public function testRenameUpdatesNameAndTouchesUpdatedAt(): void
  {
    $organization = $this->reconstitutedOrganization();
    $originalUpdatedAt = $organization->updatedAt();

    $organization->rename(new OrganizationName('Renamed Org'));

    self::assertSame('Renamed Org', (string) $organization->name());
    self::assertGreaterThan($originalUpdatedAt, $organization->updatedAt());
  }

  #[Test]
  public function testChangeSlugUpdatesSlug(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeSlug(new OrganizationSlug('new-slug'));

    self::assertSame('new-slug', (string) $organization->slug());
  }

  #[Test]
  public function testStatusTransitions(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->deactivate();
    self::assertSame(OrganizationStatus::SUSPENDED, $organization->status());
    self::assertFalse($organization->isActive());

    $organization->activate();
    self::assertSame(OrganizationStatus::ACTIVE, $organization->status());
    self::assertTrue($organization->isActive());

    $organization->archive();
    self::assertSame(OrganizationStatus::ARCHIVED, $organization->status());
    self::assertTrue($organization->isArchived());

    $organization->restore();
    self::assertSame(OrganizationStatus::ACTIVE, $organization->status());
    self::assertFalse($organization->isArchived());
  }

  #[Test]
  public function testChangeDescriptionTrimsValue(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeDescription('  Spaced description  ');

    self::assertSame('Spaced description', $organization->description());
  }

  #[Test]
  public function testChangeDescriptionNormalizesBlankToNull(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeDescription('   ');
    self::assertNull($organization->description());

    $organization->changeDescription('set');
    $organization->changeDescription(null);
    self::assertNull($organization->description());
  }

  #[Test]
  public function testLogoMutators(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->setLogoUrl('https://cdn.example.test/new.png');
    self::assertSame('https://cdn.example.test/new.png', $organization->logoUrl());

    $organization->removeLogo();
    self::assertNull($organization->logoUrl());
  }

  #[Test]
  public function testChangePlanAssignsPlanId(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changePlan(PlanId::fromString(self::PLAN_ID));

    self::assertSame(self::PLAN_ID, (string) $organization->planId());
  }

  #[Test]
  public function testUpdateNotificationSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->updateNotificationSettings(new OrganizationNotificationSettings(emailEnabled: false));

    self::assertFalse($organization->settings()->notifications->emailEnabled);
  }

  #[Test]
  public function testUpdateRegionalSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();
    $regional = new OrganizationRegionalSettings();

    $organization->updateRegionalSettings($regional);

    self::assertSame($regional, $organization->settings()->regional);
  }

  #[Test]
  public function testUpdateComplianceSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();
    $compliance = new OrganizationComplianceSettings();

    $organization->updateComplianceSettings($compliance);

    self::assertSame($compliance, $organization->settings()->compliance);
  }

  #[Test]
  public function testUpdateAutomationSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();
    $automation = new OrganizationAutomationSettings();

    $organization->updateAutomationSettings($automation);

    self::assertSame($automation, $organization->settings()->automation);
  }

  #[Test]
  public function testUpdateApprovalSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();
    $approval = new OrganizationApprovalSettings();

    $organization->updateApprovalSettings($approval);

    self::assertSame($approval, $organization->settings()->approval);
  }

  #[Test]
  public function testUpdateAssistantSettingsReplacesSubSection(): void
  {
    $organization = $this->reconstitutedOrganization();
    $assistant = new OrganizationAssistantSettings();

    $organization->updateAssistantSettings($assistant);

    self::assertSame($assistant, $organization->settings()->assistant);
  }

  #[Test]
  public function testChangeCountrySetsAndClears(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeCountry(new OrganizationCountry('us'));
    self::assertSame('US', (string) $organization->country());

    $organization->changeCountry(null);
    self::assertNull($organization->country());
  }

  #[Test]
  public function testChangeLegalTypeSetsAndClears(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeLegalType(OrganizationLegalType::NON_PROFIT_ASSOCIATION);
    self::assertSame(OrganizationLegalType::NON_PROFIT_ASSOCIATION, $organization->legalType());

    $organization->changeLegalType(null);
    self::assertNull($organization->legalType());
  }

  #[Test]
  public function testChangeLegalNameTrimsAndNormalizesBlankToNull(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeLegalName('  Acme SAS  ');
    self::assertSame('Acme SAS', $organization->legalName());

    $organization->changeLegalName('   ');
    self::assertNull($organization->legalName());

    $organization->changeLegalName(null);
    self::assertNull($organization->legalName());
  }

  #[Test]
  public function testChangeRegistrationNumberSetsAndClears(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeRegistrationNumber(new OrganizationRegistrationNumber('RCS-999'));
    self::assertSame('RCS-999', (string) $organization->registrationNumber());

    $organization->changeRegistrationNumber(null);
    self::assertNull($organization->registrationNumber());
  }

  #[Test]
  public function testChangeVatNumberSetsAndClears(): void
  {
    $organization = $this->reconstitutedOrganization();

    $organization->changeVatNumber(new OrganizationVatNumber('FR99999999999'));
    self::assertSame('FR99999999999', (string) $organization->vatNumber());

    $organization->changeVatNumber(null);
    self::assertNull($organization->vatNumber());
  }

  /**
   * Builds a reconstituted organization anchored in the past so mutations
   * observably advance the update timestamp.
   *
   * @since 1.0.0
   *
   * @return Organization the reconstituted organization
   */
  private function reconstitutedOrganization(): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme Safety'),
      createdByUserId: self::CREATOR_ID,
      isActive: true,
      createdAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
      updatedAt: new DateTimeImmutable('2020-01-01T00:00:00+00:00'),
      ownerUserId: self::OWNER_ID,
    );
  }
}
