<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Contract\Policy;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalPolicy.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalPolicy::class)]
final class ApprovalPolicyTest extends TestCase
{
  #[Test]
  public function testExposesConstructorArguments(): void
  {
    $policy = new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'manager', 'minSeverity' => 'major']],
      allowSelfApproval: true,
      approvalTtlDays: 14,
    );

    self::assertTrue($policy->allowSelfApproval);
    self::assertSame(14, $policy->approvalTtlDays);
    self::assertSame(['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'manager', 'minSeverity' => 'major']], $policy->actionRules);
  }

  #[Test]
  public function testIsEnabledForReflectsRule(): void
  {
    $policy = new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'manager', 'minSeverity' => null]],
      allowSelfApproval: false,
      approvalTtlDays: 7,
    );

    self::assertTrue($policy->isEnabledFor('nc_waiver'));
    self::assertFalse($policy->isEnabledFor('equipment_decommission'));
  }

  #[Test]
  public function testMinApproverRoleForReturnsRuleValue(): void
  {
    $policy = new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'manager', 'minSeverity' => null]],
      allowSelfApproval: false,
      approvalTtlDays: 7,
    );

    self::assertSame('manager', $policy->minApproverRoleFor('nc_waiver'));
  }

  #[Test]
  public function testMinApproverRoleForDefaultsToAdminWhenMissing(): void
  {
    $policy = new ApprovalPolicy(actionRules: [], allowSelfApproval: false, approvalTtlDays: 7);

    self::assertSame('admin', $policy->minApproverRoleFor('nc_waiver'));
  }

  #[Test]
  public function testMinSeverityForReturnsThresholdOrNull(): void
  {
    $policy = new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'manager', 'minSeverity' => 'critical']],
      allowSelfApproval: false,
      approvalTtlDays: 7,
    );

    self::assertSame('critical', $policy->minSeverityFor('nc_waiver'));
    self::assertNull($policy->minSeverityFor('equipment_decommission'));
  }
}
