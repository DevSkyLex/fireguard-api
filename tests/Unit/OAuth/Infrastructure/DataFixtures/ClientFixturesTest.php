<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Infrastructure\DataFixtures;

use OAuth\Infrastructure\DataFixtures\ClientFixtures;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ClientFixturesTest.
 *
 * @category DataFixtures Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ClientFixtures::class)]
final class ClientFixturesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGetGroupsReturnsOAuthGroup(): void
  {
    self::assertSame(['client', 'auth-seed'], ClientFixtures::getGroups());
  }
  // #endregion
}
