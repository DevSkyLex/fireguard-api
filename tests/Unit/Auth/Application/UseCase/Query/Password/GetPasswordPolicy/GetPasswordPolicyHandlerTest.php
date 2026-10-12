<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\UseCase\Query\Password\GetPasswordPolicy;

use Auth\Application\UseCase\Query\Password\GetPasswordPolicy\{GetPasswordPolicyHandler, GetPasswordPolicyQuery};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\Adapter\Policy\ConfiguredPasswordPolicyAdapter;

/**
 * Class GetPasswordPolicyHandlerTest
 *
 * Checks the query projects actual owner configuration without an authentication dependency.
 *
 * @category Handler Tests
 */
#[CoversClass(GetPasswordPolicyHandler::class)]
final class GetPasswordPolicyHandlerTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testQueryPublishesConfiguredRequirements(): void
  {
    $policy = new ConfiguredPasswordPolicyAdapter(4, 16, false, true, false, '#');
    $result = new GetPasswordPolicyHandler($policy)(new GetPasswordPolicyQuery());
    self::assertEquals($policy->describe(), $result->requirements);
  }
  // #endregion
}
