<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Application\Contract\Provisioning;

use Equipment\Application\Contract\Provisioning\ProvisionOutcome;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function count;

/**
 * Test ProvisionOutcomeTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ProvisionOutcome::class)]
final class ProvisionOutcomeTest extends TestCase
{
  #[Test]
  public function itDefinesTheExpectedCases(): void
  {
    self::assertSame(3, count(ProvisionOutcome::cases()));
    self::assertSame('CREATED', ProvisionOutcome::CREATED->name);
    self::assertSame('QUOTA_EXCEEDED', ProvisionOutcome::QUOTA_EXCEEDED->name);
    self::assertSame('INVALID', ProvisionOutcome::INVALID->name);
  }
}
