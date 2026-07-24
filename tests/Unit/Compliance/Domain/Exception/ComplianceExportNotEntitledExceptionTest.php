<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Domain\Exception;

use Compliance\Domain\Exception\ComplianceExportNotEntitledException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test ComplianceExportNotEntitledExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceExportNotEntitledException::class)]
final class ComplianceExportNotEntitledExceptionTest extends TestCase
{
  #[Test]
  public function testPlanTooLowBuildsAMessageMentioningTheOrganization(): void
  {
    $exception = ComplianceExportNotEntitledException::planTooLow('org-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'org-42'));
  }

  #[Test]
  public function testPlanTooLowMessageMentionsTheUpgradePath(): void
  {
    $exception = ComplianceExportNotEntitledException::planTooLow('org-42');

    self::assertTrue(str_contains($exception->getMessage(), 'pro'));
    self::assertTrue(str_contains($exception->getMessage(), 'max'));
  }
}
