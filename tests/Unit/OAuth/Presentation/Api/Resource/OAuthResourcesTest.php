<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Resource;

use OAuth\Presentation\Api\Resource\{ClientResource, DiscoveryResource, OAuth2Resource};
use PHPUnit\Framework\Attributes\{CoversNothing, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OAuthResourcesTest.
 *
 * @category Resource Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversNothing]
final class OAuthResourcesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testResourcesCanBeInstantiated(): void
  {
    self::assertInstanceOf(OAuth2Resource::class, new OAuth2Resource());
    self::assertInstanceOf(ClientResource::class, new ClientResource());
    self::assertInstanceOf(DiscoveryResource::class, new DiscoveryResource());
  }
  // #endregion
}
