<?php

declare(strict_types=1);

namespace Tests\Unit\User\Infrastructure\DataFixtures;

use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use User\Infrastructure\DataFixtures\UserFixtures;

/**
 * Test UserFixturesTest.
 *
 * @category DataFixtures Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UserFixtures::class)]
final class UserFixturesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetGroupsReturnsUserGroup(): void
  {
    self::assertSame(['user', 'auth-seed'], UserFixtures::getGroups());
  }
  // #endregion
}
