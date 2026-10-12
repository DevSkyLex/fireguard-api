<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\Adapter\Policy;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

use function array_values;
use function get_object_vars;

/**
 * Class ConfiguredPasswordPolicyAdapterTest
 *
 * Verifies publication and enforcement share the actual deployment rules.
 *
 * @category Adapter Tests
 */
#[CoversClass(ConfiguredPasswordPolicyAdapter::class)]
final class ConfiguredPasswordPolicyAdapterTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDefaultDescriptionAndValidationAgree(): void
  {
    $policy = new ConfiguredPasswordPolicyAdapter();
    self::assertSame([8, 128, true, true, true, '@$!%*?&#', 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789@$!%*?&#'], array_values(get_object_vars($policy->describe())));
    $policy->assertAcceptable('Abcdef1!');
    self::assertSame(['complexity'], $policy->violations('abcdefgh'));
  }

  #[Test]
  public function testCustomDescriptionAndEnforcementAgree(): void
  {
    $policy = new ConfiguredPasswordPolicyAdapter(4, 12, false, false, true, '');
    self::assertSame([4, 12, false, false, true, '', null], array_values(get_object_vars($policy->describe())));
    $policy->assertAcceptable('abc1');
    $this->expectException(InvalidValueException::class);
    $policy->assertAcceptable('abcd');
  }

  #[Test]
  public function testInvalidDeploymentConfigurationCannotConstructAdapter(): void
  {
    $this->expectException(InvalidValueException::class);
    new ConfiguredPasswordPolicyAdapter(12, 8);
  }
  // #endregion
}
