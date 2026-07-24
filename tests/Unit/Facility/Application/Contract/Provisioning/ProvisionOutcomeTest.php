<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\Contract\Provisioning;

use Facility\Application\Contract\Provisioning\ProvisionOutcome;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function array_map;

/**
 * Test ProvisionOutcome.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionOutcome::class)]
final class ProvisionOutcomeTest extends TestCase
{
  #[Test]
  public function testEnumExposesEveryCase(): void
  {
    $names = array_map(static fn (ProvisionOutcome $case): string => $case->name, ProvisionOutcome::cases());

    self::assertSame(['CREATED', 'QUOTA_EXCEEDED', 'INVALID'], $names);
  }

  #[Test]
  public function testCasesAreDistinct(): void
  {
    self::assertNotSame(ProvisionOutcome::CREATED, ProvisionOutcome::INVALID);
    self::assertNotSame(ProvisionOutcome::QUOTA_EXCEEDED, ProvisionOutcome::INVALID);
  }
}
