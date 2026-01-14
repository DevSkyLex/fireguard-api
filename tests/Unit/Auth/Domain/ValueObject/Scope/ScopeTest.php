<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Scope;

use Auth\Domain\ValueObject\Scope\Scope;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ScopeTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: Scope::class)]
final class ScopeTest extends TestCase
{
  // #region Methods
  /**
   * Method testValuesReturnsAllCases.
   *
   * Tests that values returns all enum values.
   */
  #[Test]
  public function testValuesReturnsAllCases(): void
  {
    $expected = [
      Scope::OPENID->value,
      Scope::PROFILE->value,
      Scope::EMAIL->value,
      Scope::PHONE->value,
      Scope::ADDRESS->value,
      Scope::READ->value,
      Scope::WRITE->value,
      Scope::ADMIN->value,
      Scope::DELETE->value,
    ];

    $this->assertSame($expected, Scope::values());
  }
  // #endregion
}
