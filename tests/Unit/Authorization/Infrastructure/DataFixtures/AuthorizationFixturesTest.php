<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\DataFixtures;

use Authorization\Infrastructure\DataFixtures\AuthorizationFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AuthorizationFixturesTest.
 *
 * @category DataFixtures Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AuthorizationFixtures::class)]
final class AuthorizationFixturesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetGroupsReturnsAuthorizationGroup(): void
  {
    self::assertSame(['authorization', 'auth-seed'], AuthorizationFixtures::getGroups());
  }
  // #endregion
}
