<?php

declare(strict_types=1);

namespace Tests\Unit\Automation\Application\Contract\Policy;

use Automation\Application\Contract\Policy\AutomationPolicy;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AutomationPolicy.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AutomationPolicy::class)]
final class AutomationPolicyTest extends TestCase
{
  #[Test]
  public function itRoundTripsItsProperties(): void
  {
    $policy = new AutomationPolicy(
      true,
      ['critical' => 7, 'major' => 30],
    );

    self::assertTrue($policy->autoCreateInterventionOnCriticalNc);
    self::assertSame(['critical' => 7, 'major' => 30], $policy->nonConformitySlaDays);
  }

  #[Test]
  public function itSupportsADisabledPolicyWithNoSla(): void
  {
    $policy = new AutomationPolicy(false, []);

    self::assertFalse($policy->autoCreateInterventionOnCriticalNc);
    self::assertSame([], $policy->nonConformitySlaDays);
  }
}
