<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Application\Service;

use Auth\Application\Service\SecurityUserCacheKeys;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test SecurityUserCacheKeysTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SecurityUserCacheKeys::class)]
final class SecurityUserCacheKeysTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testUserPrefixesTheIdentifier(): void
  {
    self::assertSame(
      'auth.security_user.0199a7c1-0000-7000-8000-000000000001',
      SecurityUserCacheKeys::user('0199a7c1-0000-7000-8000-000000000001'),
    );
  }

  #[Test]
  public function testUserHandlesEmptyIdentifier(): void
  {
    self::assertSame('auth.security_user.', SecurityUserCacheKeys::user(''));
  }
  // #endregion
}
