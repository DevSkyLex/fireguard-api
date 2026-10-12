<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Adapter\Policy;

use Auth\Infrastructure\Adapter\Policy\ConfiguredAuthenticationPolicyAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Class ConfiguredAuthenticationPolicyAdapterTest
 *
 * Pins the installed choices and proves alternate deployment configuration is represented.
 *
 * @category Adapter Tests
 */
#[CoversClass(ConfiguredAuthenticationPolicyAdapter::class)]
final class ConfiguredAuthenticationPolicyAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testExistingProjectChoicesRemainDefaults(): void
  {
    $policy = new ConfiguredAuthenticationPolicyAdapter();
    self::assertTrue($policy->signupEnabled());
    self::assertTrue($policy->emailVerificationRequired());
    self::assertTrue($policy->mfaRequired());
    self::assertTrue($policy->trustedDeviceMfaBypassEnabled());
    self::assertTrue($policy->federatedProvisioningEnabled());
  }

  #[Test]
  public function testEachChoiceCanBeOverridden(): void
  {
    $policy = new ConfiguredAuthenticationPolicyAdapter(false, false, false, false, false);
    self::assertFalse($policy->signupEnabled());
    self::assertFalse($policy->emailVerificationRequired());
    self::assertFalse($policy->mfaRequired());
    self::assertFalse($policy->trustedDeviceMfaBypassEnabled());
    self::assertFalse($policy->federatedProvisioningEnabled());
  }
  // #endregion
}
