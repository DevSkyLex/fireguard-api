<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\ValueObject\OrganizationNotificationSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationNotificationSettings.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationNotificationSettings::class)]
final class OrganizationNotificationSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsAreAllEnabled(): void
  {
    $settings = new OrganizationNotificationSettings();

    self::assertTrue($settings->emailEnabled);
    self::assertTrue($settings->inAppEnabled);
    self::assertTrue($settings->interventionPublished);
    self::assertTrue($settings->interventionAssigned);
    self::assertTrue($settings->inspectionDue);
    self::assertTrue($settings->nonConformityOpened);
    self::assertTrue($settings->nonConformitySlaBreached);
    self::assertTrue($settings->memberInvited);
    self::assertTrue($settings->weeklyDigest);
  }

  #[Test]
  public function testToArrayExposesSnakeCaseKeys(): void
  {
    $settings = new OrganizationNotificationSettings(emailEnabled: false);

    self::assertSame([
      'email_enabled' => false,
      'in_app_enabled' => true,
      'intervention_published' => true,
      'intervention_assigned' => true,
      'inspection_due' => true,
      'non_conformity_opened' => true,
      'non_conformity_sla_breached' => true,
      'member_invited' => true,
      'weekly_digest' => true,
    ], $settings->toArray());
  }

  #[Test]
  public function testFromArrayFallsBackToDefaultsForMissingFlags(): void
  {
    $settings = OrganizationNotificationSettings::fromArray(['email_enabled' => false]);

    self::assertFalse($settings->emailEnabled);
    self::assertTrue($settings->inAppEnabled);
  }

  #[Test]
  public function testToArrayFromArrayRoundTrip(): void
  {
    $original = new OrganizationNotificationSettings(
      emailEnabled: false,
      inAppEnabled: false,
      interventionPublished: false,
      interventionAssigned: true,
      inspectionDue: false,
      nonConformityOpened: true,
      nonConformitySlaBreached: false,
      memberInvited: false,
      weeklyDigest: false,
    );

    $restored = OrganizationNotificationSettings::fromArray($original->toArray());

    self::assertEquals($original, $restored);
  }
}
