<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Application\Service;

use Compliance\Application\Service\ComplianceStatusPolicy;
use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

#[CoversClass(ComplianceStatusPolicy::class)]
final class ComplianceStatusPolicyTest extends TestCase
{
  #[Test]
  public function testGradeReturnsNonCompliantWhenEquipmentIsOverdue(): void
  {
    self::assertSame(
      ComplianceStatus::NON_COMPLIANT,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 1,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 5,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 0,
      ),
    );
  }

  #[Test]
  public function testGradeReturnsNonCompliantWhenAnOpenCriticalNonConformityExists(): void
  {
    self::assertSame(
      ComplianceStatus::NON_COMPLIANT,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 5,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 1,
      ),
    );
  }

  #[Test]
  public function testGradeReturnsAtRiskWhenEquipmentIsDueSoonWithNoHardBreach(): void
  {
    self::assertSame(
      ComplianceStatus::AT_RISK,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 2,
        trackedEquipmentCount: 5,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 0,
      ),
    );
  }

  #[Test]
  public function testGradeReturnsAtRiskWhenAnOpenHighNonConformityExistsWithNoHardBreach(): void
  {
    self::assertSame(
      ComplianceStatus::AT_RISK,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 5,
        openHighNonConformityCount: 1,
        openCriticalNonConformityCount: 0,
      ),
    );
  }

  #[Test]
  public function testGradeReturnsCompliantWhenTrackedEquipmentExistsAndNoIssue(): void
  {
    self::assertSame(
      ComplianceStatus::COMPLIANT,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 5,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 0,
      ),
    );
  }

  #[Test]
  public function testGradeReturnsNotApplicableWhenNoTrackedEquipmentAndNoIssue(): void
  {
    self::assertSame(
      ComplianceStatus::NOT_APPLICABLE,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 0,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 0,
      ),
    );
  }

  #[Test]
  public function testGradePrioritizesNonCompliantOverHardBreachEvenWithoutTrackedEquipment(): void
  {
    self::assertSame(
      ComplianceStatus::NON_COMPLIANT,
      ComplianceStatusPolicy::grade(
        overdueEquipmentCount: 0,
        dueSoonEquipmentCount: 0,
        trackedEquipmentCount: 0,
        openHighNonConformityCount: 0,
        openCriticalNonConformityCount: 3,
      ),
    );
  }

  #[Test]
  public function testWorstOfReturnsWorstNonApplicableExcludedStatus(): void
  {
    self::assertSame(ComplianceStatus::NON_COMPLIANT, ComplianceStatusPolicy::worstOf([
      ComplianceStatus::COMPLIANT,
      ComplianceStatus::NOT_APPLICABLE,
      ComplianceStatus::NON_COMPLIANT,
      ComplianceStatus::AT_RISK,
    ]));
  }

  #[Test]
  public function testWorstOfReturnsNotApplicableWhenEveryFacilityIsNotApplicable(): void
  {
    self::assertSame(ComplianceStatus::NOT_APPLICABLE, ComplianceStatusPolicy::worstOf([
      ComplianceStatus::NOT_APPLICABLE,
      ComplianceStatus::NOT_APPLICABLE,
    ]));
  }

  #[Test]
  public function testWorstOfReturnsNotApplicableForAnEmptyList(): void
  {
    self::assertSame(ComplianceStatus::NOT_APPLICABLE, ComplianceStatusPolicy::worstOf([]));
  }

  #[Test]
  public function testWorstOfReturnsCompliantWhenNoRiskAmongApplicableFacilities(): void
  {
    self::assertSame(ComplianceStatus::COMPLIANT, ComplianceStatusPolicy::worstOf([
      ComplianceStatus::COMPLIANT,
      ComplianceStatus::NOT_APPLICABLE,
    ]));
  }
}
