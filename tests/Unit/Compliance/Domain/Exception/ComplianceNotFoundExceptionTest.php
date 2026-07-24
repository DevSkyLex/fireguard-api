<?php

declare(strict_types=1);

namespace Tests\Unit\Compliance\Domain\Exception;

use Compliance\Domain\Exception\ComplianceNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

use function str_contains;

/**
 * Test ComplianceNotFoundExceptionTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ComplianceNotFoundException::class)]
final class ComplianceNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testFacilityNotFoundBuildsAMessageMentioningTheFacility(): void
  {
    $exception = ComplianceNotFoundException::facilityNotFound('facility-77');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertTrue(str_contains($exception->getMessage(), 'facility-77'));
  }
}
