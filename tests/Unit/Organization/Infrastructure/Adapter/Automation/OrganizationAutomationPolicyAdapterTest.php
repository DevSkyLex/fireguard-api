<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Automation;

use Automation\Application\Contract\Policy\AutomationPolicy;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationComplianceDefaults;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationAutomationSettings,
  OrganizationComplianceSettings,
  OrganizationId,
  OrganizationName,
  OrganizationSettings
};
use Organization\Infrastructure\Adapter\Automation\OrganizationAutomationPolicyAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationAutomationPolicyAdapterTest.
 *
 * Automation decides on its own whether a critical non-conformity opens an
 * intervention, and it asks this adapter. Every path that cannot answer —
 * a malformed identifier, an unknown organization, one that never
 * configured its settings — must fall back to "automation off" with the
 * default SLA, because guessing "on" would create work nobody asked for.
 *
 * @category Adapter Tests
 *
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationAutomationPolicyAdapter::class)]
final class OrganizationAutomationPolicyAdapterTest extends TestCase
{
  // #region Constants
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655481001';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655481002';
  // #endregion

  // #region Methods
  #[Test]
  public function testPolicyReflectsTheConfiguredAutomationSettings(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      ownerUserId: self::OWNER_USER_ID,
      settings: new OrganizationSettings(
        compliance: new OrganizationComplianceSettings(nonConformitySlaDays: ['critical' => 3]),
        automation: new OrganizationAutomationSettings(autoCreateInterventionOnCriticalNc: true),
      ),
    );

    $policy = $this->adapterFor($organization)->policyFor(self::ORGANIZATION_ID);

    self::assertInstanceOf(AutomationPolicy::class, $policy);
    self::assertTrue($policy->autoCreateInterventionOnCriticalNc);
    self::assertSame(3, $policy->nonConformitySlaDays['critical']);
  }

  #[Test]
  public function testPolicyFallsBackWhenTheOrganizationHasNoSettings(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Test'),
      ownerUserId: self::OWNER_USER_ID,
    );

    $this->assertIsDefaultPolicy($this->adapterFor($organization)->policyFor(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testPolicyFallsBackWhenTheOrganizationIsUnknown(): void
  {
    $this->assertIsDefaultPolicy($this->adapterFor(null)->policyFor(self::ORGANIZATION_ID));
  }

  #[Test]
  public function testPolicyFallsBackWhenTheIdentifierIsMalformed(): void
  {
    $this->assertIsDefaultPolicy($this->adapterFor(null)->policyFor('not-a-uuid'));
  }

  private function assertIsDefaultPolicy(AutomationPolicy $policy): void
  {
    self::assertFalse($policy->autoCreateInterventionOnCriticalNc);
    self::assertSame(
      OrganizationComplianceDefaults::NON_CONFORMITY_SLA_DAYS,
      $policy->nonConformitySlaDays,
    );
  }

  private function adapterFor(?Organization $organization): OrganizationAutomationPolicyAdapter
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    return new OrganizationAutomationPolicyAdapter($repository);
  }
  // #endregion
}
