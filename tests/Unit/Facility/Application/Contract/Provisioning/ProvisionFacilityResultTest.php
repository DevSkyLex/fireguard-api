<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Contract\Provisioning;

use Facility\Application\Contract\Provisioning\{ProvisionFacilityResult, ProvisionOutcome};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ProvisionFacilityResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionFacilityResult::class)]
final class ProvisionFacilityResultTest extends TestCase
{
  #[Test]
  public function testCreatedResultRoundTrips(): void
  {
    $result = new ProvisionFacilityResult(
      outcome: ProvisionOutcome::CREATED,
      resourceId: 'fac-1',
    );

    self::assertSame(ProvisionOutcome::CREATED, $result->outcome);
    self::assertSame('fac-1', $result->resourceId);
    self::assertNull($result->message);
  }

  #[Test]
  public function testFailureResultCarriesMessage(): void
  {
    $result = new ProvisionFacilityResult(
      outcome: ProvisionOutcome::QUOTA_EXCEEDED,
      message: 'Plan quota exceeded.',
    );

    self::assertSame(ProvisionOutcome::QUOTA_EXCEEDED, $result->outcome);
    self::assertNull($result->resourceId);
    self::assertSame('Plan quota exceeded.', $result->message);
  }
}
