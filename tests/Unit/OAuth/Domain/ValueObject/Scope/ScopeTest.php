<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Scope;

use OAuth\Domain\ValueObject\Scope\Scope;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ScopeTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Scope::class)]
final class ScopeTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testValuesReturnsList(): void
  {
    $values = Scope::values();

    self::assertContains('OPENID', $values);
    self::assertContains('READ', $values);
  }
  // #endregion
}
