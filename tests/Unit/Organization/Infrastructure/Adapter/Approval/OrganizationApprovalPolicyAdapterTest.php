<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Infrastructure\Adapter\Approval;

use Approval\Application\Contract\Action\ApprovalActionTypes;
use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Catalog\OrganizationApprovalDefaults;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{
  OrganizationApprovalSettings,
  OrganizationId,
  OrganizationName,
  OrganizationSettings
};
use Organization\Infrastructure\Adapter\Approval\OrganizationApprovalPolicyAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OrganizationApprovalPolicyAdapter.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationApprovalPolicyAdapter::class)]
final class OrganizationApprovalPolicyAdapterTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655441810';

  #[Test]
  public function testAMalformedOrganizationIdYieldsTheDisabledPolicy(): void
  {
    $policy = $this->adapter(null)->policyFor('not-a-uuid');

    self::assertFalse($policy->isEnabledFor(ApprovalActionTypes::NC_WAIVER));
    self::assertSame(OrganizationApprovalDefaults::MIN_APPROVER_ROLE, $policy->minApproverRoleFor(ApprovalActionTypes::NC_WAIVER));
    self::assertNull($policy->minSeverityFor(ApprovalActionTypes::NC_WAIVER));
    self::assertSame(OrganizationApprovalDefaults::ALLOW_SELF_APPROVAL, $policy->allowSelfApproval);
    self::assertSame(OrganizationApprovalDefaults::APPROVAL_TTL_DAYS, $policy->approvalTtlDays);
  }

  #[Test]
  public function testAnUnknownOrganizationYieldsTheDisabledPolicy(): void
  {
    $policy = $this->adapter(null)->policyFor(self::ORGANIZATION_ID);

    self::assertFalse($policy->isEnabledFor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION));
    self::assertCount(2, $policy->actionRules);
  }

  #[Test]
  public function testStoredSettingsAreProjectedOntoEveryActionType(): void
  {
    $organization = $this->organizationWith(new OrganizationApprovalSettings(
      actionRules: [
        ApprovalActionTypes::EQUIPMENT_DECOMMISSION => [
          'enabled' => true,
          'minApproverRole' => 'member',
          'minSeverity' => 'high',
        ],
      ],
      allowSelfApproval: true,
      approvalTtlDays: 21,
    ));

    $policy = $this->adapter($organization)->policyFor(self::ORGANIZATION_ID);

    self::assertTrue($policy->isEnabledFor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION));
    self::assertSame('member', $policy->minApproverRoleFor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION));
    self::assertSame('high', $policy->minSeverityFor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION));
    self::assertTrue($policy->allowSelfApproval);
    self::assertSame(21, $policy->approvalTtlDays);
  }

  #[Test]
  public function testAnUnconfiguredWaiverFallsBackToTheCatalogSeverity(): void
  {
    $policy = $this->adapter($this->organizationWith(new OrganizationApprovalSettings()))
      ->policyFor(self::ORGANIZATION_ID);

    self::assertFalse($policy->isEnabledFor(ApprovalActionTypes::NC_WAIVER));
    self::assertSame(
      OrganizationApprovalDefaults::NC_WAIVER_MIN_SEVERITY,
      $policy->minSeverityFor(ApprovalActionTypes::NC_WAIVER),
    );
    self::assertNull($policy->minSeverityFor(ApprovalActionTypes::EQUIPMENT_DECOMMISSION));
  }

  /**
   * Builds the adapter over a repository stub returning the given organization.
   */
  private function adapter(?Organization $organization): OrganizationApprovalPolicyAdapter
  {
    $repository = $this->createStub(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);

    return new OrganizationApprovalPolicyAdapter($repository);
  }

  /**
   * Reconstitutes an organization carrying the given approval settings.
   */
  private function organizationWith(OrganizationApprovalSettings $approval): Organization
  {
    return Organization::reconstitute(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Acme'),
      createdByUserId: 'user-1',
      isActive: true,
      createdAt: new DateTimeImmutable('2026-01-01T09:00:00+00:00'),
      settings: new OrganizationSettings(approval: $approval),
    );
  }
}
