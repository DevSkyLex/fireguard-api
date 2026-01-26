<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Scope;

use OAuth\Domain\ValueObject\Scope\DefaultScopes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DefaultScopesTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DefaultScopes::class)]
final class DefaultScopesTest extends TestCase
{
  // #region Methods
  /**
   * Method testDefaultScopesAreDefined.
   */
  #[Test]
  public function testDefaultScopesAreDefined(): void
  {
    $this->assertContains('OPENID', DefaultScopes::USER_SCOPES);
    $this->assertContains('READ', DefaultScopes::CLIENT_SCOPES);
    $this->assertContains('EMAIL', DefaultScopes::OPENID_SCOPES);
  }
  // #endregion
}
