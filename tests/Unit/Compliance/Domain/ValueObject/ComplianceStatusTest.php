<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Domain\ValueObject;

use Compliance\Domain\ValueObject\ComplianceStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ComplianceStatusTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceStatus::class)]
final class ComplianceStatusTest extends TestCase
{
  #[Test]
  public function testCasesExposeTheExpectedStringValues(): void
  {
    self::assertSame('compliant', ComplianceStatus::COMPLIANT->value);
    self::assertSame('at_risk', ComplianceStatus::AT_RISK->value);
    self::assertSame('non_compliant', ComplianceStatus::NON_COMPLIANT->value);
    self::assertSame('not_applicable', ComplianceStatus::NOT_APPLICABLE->value);
  }

  #[Test]
  public function testValuesReturnsEveryCaseValue(): void
  {
    self::assertSame(
      ['compliant', 'at_risk', 'non_compliant', 'not_applicable'],
      ComplianceStatus::values(),
    );
  }

  #[Test]
  public function testSeverityRankOrdersWorstHighest(): void
  {
    self::assertSame(3, ComplianceStatus::NON_COMPLIANT->severityRank());
    self::assertSame(2, ComplianceStatus::AT_RISK->severityRank());
    self::assertSame(1, ComplianceStatus::COMPLIANT->severityRank());
    self::assertSame(0, ComplianceStatus::NOT_APPLICABLE->severityRank());
  }

  #[Test]
  public function testSeverityRankIsStrictlyMonotonic(): void
  {
    self::assertGreaterThan(
      ComplianceStatus::AT_RISK->severityRank(),
      ComplianceStatus::NON_COMPLIANT->severityRank(),
    );
    self::assertGreaterThan(
      ComplianceStatus::COMPLIANT->severityRank(),
      ComplianceStatus::AT_RISK->severityRank(),
    );
    self::assertGreaterThan(
      ComplianceStatus::NOT_APPLICABLE->severityRank(),
      ComplianceStatus::COMPLIANT->severityRank(),
    );
  }
}
