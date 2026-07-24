<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\ValueObject;

use Organization\Domain\Catalog\OrganizationApprovalDefaults;
use Organization\Domain\ValueObject\OrganizationApprovalSettings;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OrganizationApprovalSettingsTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationApprovalSettings::class)]
#[CoversClass(OrganizationApprovalDefaults::class)]
final class OrganizationApprovalSettingsTest extends TestCase
{
  #[Test]
  public function testDefaultsAreEverythingDisabled(): void
  {
    $settings = new OrganizationApprovalSettings();

    self::assertSame([], $settings->actionRules);
    self::assertFalse($settings->allowSelfApproval);
    self::assertSame(OrganizationApprovalDefaults::APPROVAL_TTL_DAYS, $settings->approvalTtlDays);

    $rule = $settings->ruleFor('nc_waiver');
    self::assertFalse($rule['enabled']);
    self::assertSame(OrganizationApprovalDefaults::MIN_APPROVER_ROLE, $rule['minApproverRole']);
    self::assertNull($rule['minSeverity']);
  }

  #[Test]
  public function testConstructorRejectsUnknownMinApproverRole(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'superadmin', 'minSeverity' => null],
    ]);
  }

  #[Test]
  public function testConstructorRejectsUnknownSeverity(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'catastrophic'],
    ]);
  }

  #[Test]
  public function testConstructorRejectsTtlOutOfBounds(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationApprovalSettings(approvalTtlDays: 0);
  }

  #[Test]
  public function testToArrayRoundTripsThroughFromArray(): void
  {
    $settings = new OrganizationApprovalSettings(
      actionRules: ['equipment_decommission' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => null]],
      allowSelfApproval: true,
      approvalTtlDays: 30,
    );

    $restored = OrganizationApprovalSettings::fromArray($settings->toArray());

    self::assertSame($settings->actionRules, $restored->actionRules);
    self::assertTrue($restored->allowSelfApproval);
    self::assertSame(30, $restored->approvalTtlDays);
  }

  #[Test]
  public function testFromArrayToleratesEmptyPayload(): void
  {
    $settings = OrganizationApprovalSettings::fromArray([]);

    self::assertSame([], $settings->actionRules);
    self::assertFalse($settings->allowSelfApproval);
    self::assertSame(OrganizationApprovalDefaults::APPROVAL_TTL_DAYS, $settings->approvalTtlDays);
  }

  #[Test]
  public function testMergedWithAddsAndPartiallyUpdatesARule(): void
  {
    $settings = new OrganizationApprovalSettings();

    $merged = $settings->mergedWith([
      'action_rules' => [
        'nc_waiver' => ['enabled' => true, 'min_approver_role' => 'admin', 'min_severity' => 'critical'],
      ],
      'approval_ttl_days' => 7,
    ]);

    $rule = $merged->ruleFor('nc_waiver');
    self::assertTrue($rule['enabled']);
    self::assertSame('admin', $rule['minApproverRole']);
    self::assertSame('critical', $rule['minSeverity']);
    self::assertSame(7, $merged->approvalTtlDays);

    // A subsequent partial update touching only `enabled` leaves the other fields unchanged.
    $mergedAgain = $merged->mergedWith([
      'action_rules' => [
        'nc_waiver' => ['enabled' => false],
      ],
    ]);

    $ruleAgain = $mergedAgain->ruleFor('nc_waiver');
    self::assertFalse($ruleAgain['enabled']);
    self::assertSame('admin', $ruleAgain['minApproverRole']);
    self::assertSame('critical', $ruleAgain['minSeverity']);
  }

  #[Test]
  public function testMergedWithNullEntryRemovesTheCustomization(): void
  {
    $settings = new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical'],
    ]);

    $merged = $settings->mergedWith([
      'action_rules' => ['nc_waiver' => null],
    ]);

    self::assertSame([], $merged->actionRules);
    $rule = $merged->ruleFor('nc_waiver');
    self::assertFalse($rule['enabled']);
  }

  #[Test]
  public function testConstructorRejectsEmptyActionTypeKey(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationApprovalSettings(actionRules: [
      '' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => null],
    ]);
  }

  #[Test]
  public function testConstructorRejectsTtlAboveMaximum(): void
  {
    $this->expectException(InvalidValueException::class);

    new OrganizationApprovalSettings(approvalTtlDays: OrganizationApprovalDefaults::MAX_APPROVAL_TTL_DAYS + 1);
  }

  #[Test]
  public function testMergedWithUpdatesScalarsWhenActionRulesKeyAbsent(): void
  {
    $settings = new OrganizationApprovalSettings();

    $merged = $settings->mergedWith([
      'allow_self_approval' => true,
      'approval_ttl_days' => 21,
    ]);

    self::assertSame([], $merged->actionRules);
    self::assertTrue($merged->allowSelfApproval);
    self::assertSame(21, $merged->approvalTtlDays);
  }

  #[Test]
  public function testMergedWithIgnoresNonArrayActionRulesPayload(): void
  {
    $settings = new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical'],
    ]);

    $merged = $settings->mergedWith(['action_rules' => 'not-an-array']);

    self::assertSame($settings->actionRules, $merged->actionRules);
  }

  #[Test]
  public function testMergedWithSkipsNonStringKeysAndNonArrayRules(): void
  {
    $settings = new OrganizationApprovalSettings();

    $merged = $settings->mergedWith([
      'action_rules' => [
        7 => ['enabled' => true],
        'nc_waiver' => 'not-an-array',
      ],
    ]);

    self::assertSame([], $merged->actionRules);
  }

  #[Test]
  public function testMergedWithClearsSeverityAndKeepsCurrentWhenFieldsOmitted(): void
  {
    $settings = new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical'],
    ]);

    $merged = $settings->mergedWith([
      'action_rules' => ['nc_waiver' => ['min_severity' => null]],
    ]);

    $rule = $merged->ruleFor('nc_waiver');
    self::assertTrue($rule['enabled']);
    self::assertSame('admin', $rule['minApproverRole']);
    self::assertNull($rule['minSeverity']);
  }

  #[Test]
  public function testMergedWithTreatsEmptyStringsAsFallback(): void
  {
    $settings = new OrganizationApprovalSettings(actionRules: [
      'nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical'],
    ]);

    $merged = $settings->mergedWith([
      'action_rules' => [
        'nc_waiver' => ['enabled' => true, 'min_approver_role' => '', 'min_severity' => ''],
      ],
    ]);

    $rule = $merged->ruleFor('nc_waiver');
    self::assertSame('admin', $rule['minApproverRole']);
    self::assertNull($rule['minSeverity']);
  }

  #[Test]
  public function testFromArrayIgnoresNonArrayActionRules(): void
  {
    $settings = OrganizationApprovalSettings::fromArray(['action_rules' => 'not-an-array']);

    self::assertSame([], $settings->actionRules);
  }

  #[Test]
  public function testFromArraySkipsInvalidEntriesAndDefaultsMissingRole(): void
  {
    $settings = OrganizationApprovalSettings::fromArray([
      'action_rules' => [
        10 => ['enabled' => true],
        'bad_rule' => 'not-an-array',
        'good' => ['enabled' => true, 'min_severity' => 'high'],
      ],
    ]);

    self::assertArrayNotHasKey('bad_rule', $settings->actionRules);
    $rule = $settings->ruleFor('good');
    self::assertTrue($rule['enabled']);
    self::assertSame(OrganizationApprovalDefaults::MIN_APPROVER_ROLE, $rule['minApproverRole']);
    self::assertSame('high', $rule['minSeverity']);
  }

  #[Test]
  public function testFromArrayFallsBackWhenTtlIsNotInt(): void
  {
    $settings = OrganizationApprovalSettings::fromArray(['approval_ttl_days' => 'thirty']);

    self::assertSame(OrganizationApprovalDefaults::APPROVAL_TTL_DAYS, $settings->approvalTtlDays);
  }
}
