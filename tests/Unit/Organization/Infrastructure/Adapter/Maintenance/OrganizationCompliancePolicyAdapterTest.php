<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Maintenance;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationComplianceSettings,
  OrganizationId,
  OrganizationName,
  OrganizationSettings
};
use Organization\Infrastructure\Adapter\Maintenance\OrganizationCompliancePolicyAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationCompliancePolicyAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationCompliancePolicyAdapter::class)]
final class OrganizationCompliancePolicyAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  #[Test]
  public function testAMalformedOrganizationIdYieldsTheDefaultPolicy(): void
  {
    $policy = $this->adapter(null)->compliancePolicy('not-a-uuid');

    self::assertSame(OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS, $policy->reminderWindowDays);
    self::assertNull($policy->periodicityFor('fire_extinguisher'));
  }

  #[Test]
  public function testAnUnknownOrganizationYieldsTheDefaultPolicy(): void
  {
    $policy = $this->adapter(null)->compliancePolicy(self::ORGANIZATION_ID);

    self::assertSame(OrganizationComplianceDefaults::REMINDER_WINDOW_DAYS, $policy->reminderWindowDays);
    self::assertNull($policy->periodicityFor('fire_extinguisher'));
  }

  #[Test]
  public function testStoredSettingsAreProjectedOntoThePolicy(): void
  {
    $organization = Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme'),
      createdByUserId: 'user-1',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      settings: new OrganizationSettings(compliance: new OrganizationComplianceSettings(
        inspectionPeriodicityDefaults: ['fire_extinguisher' => 'P6M'],
        reminderWindowDays: 15,
      )),
    );

    $policy = $this->adapter($organization)->compliancePolicy(self::ORGANIZATION_ID);

    self::assertSame(15, $policy->reminderWindowDays);
    self::assertSame('P6M', $policy->periodicityFor('fire_extinguisher'));
  }

  /**
   * Builds the adapter over a repository stub returning the given organization.
   */
  private function adapter(?Organization $organization): OrganizationCompliancePolicyAdapter
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    return new OrganizationCompliancePolicyAdapter($repository);
  }
}
