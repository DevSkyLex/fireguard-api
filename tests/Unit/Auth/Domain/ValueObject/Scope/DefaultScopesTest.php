<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Scope;

use Auth\Domain\ValueObject\Scope\DefaultScopes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test DefaultScopesTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: DefaultScopes::class)]
final class DefaultScopesTest extends TestCase
{
  // #region Methods
  /**
   * Method testDefaultScopesAreDefined.
   *
   * Tests that default scope lists are defined.
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
