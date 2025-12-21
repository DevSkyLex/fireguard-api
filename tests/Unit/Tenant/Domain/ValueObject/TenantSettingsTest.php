<?php

declare(strict_types=1);

namespace Tests\Unit\Tenant\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tenant\Domain\ValueObject\TenantSettings;

/**
 * Class TenantSettingsTest.
 *
 * Unit tests for the TenantSettings Value Object.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: TenantSettings::class)]
final class TenantSettingsTest extends TestCase
{
  // #region Methods
  /**
   * Method testDefaultValues.
   *
   * Tests that default values are correctly set.
   */
  #[Test]
  public function testDefaultValues(): void
  {
    $settings = new TenantSettings();

    $this->assertEquals(expected: 3600, actual: $settings->accessTokenTtl);
    $this->assertEquals(expected: 86400, actual: $settings->refreshTokenTtl);
    $this->assertTrue(condition: $settings->requirePkce);
    $this->assertFalse(condition: $settings->allowPublicClients);
    $this->assertEquals(expected: ['openid', 'profile', 'email'], actual: $settings->allowedScopes);
    $this->assertNull(actual: $settings->customIssuer);
  }

  /**
   * Method testCustomValues.
   *
   * Tests that custom values can be set.
   */
  #[Test]
  public function testCustomValues(): void
  {
    $settings = new TenantSettings(
      accessTokenTtl: 7200,
      refreshTokenTtl: 172800,
      requirePkce: false,
      allowPublicClients: true,
      allowedScopes: ['openid', 'profile'],
      customIssuer: 'https://custom.issuer.com',
    );

    $this->assertEquals(expected: 7200, actual: $settings->accessTokenTtl);
    $this->assertEquals(expected: 172800, actual: $settings->refreshTokenTtl);
    $this->assertFalse(condition: $settings->requirePkce);
    $this->assertTrue(condition: $settings->allowPublicClients);
    $this->assertEquals(expected: ['openid', 'profile'], actual: $settings->allowedScopes);
    $this->assertEquals(expected: 'https://custom.issuer.com', actual: $settings->customIssuer);
  }

  /**
   * Method testWithAccessTokenTtl.
   *
   * Tests the immutable withAccessTokenTtl method.
   */
  #[Test]
  public function testWithAccessTokenTtl(): void
  {
    $original = new TenantSettings(accessTokenTtl: 3600);
    $modified = $original->withAccessTokenTtl(7200);

    // Original should be unchanged
    $this->assertEquals(expected: 3600, actual: $original->accessTokenTtl);
    // Modified should have new value
    $this->assertEquals(expected: 7200, actual: $modified->accessTokenTtl);
    // Other values should be preserved
    $this->assertEquals(expected: $original->refreshTokenTtl, actual: $modified->refreshTokenTtl);
  }

  /**
   * Method testWithRefreshTokenTtl.
   *
   * Tests the immutable withRefreshTokenTtl method.
   */
  #[Test]
  public function testWithRefreshTokenTtl(): void
  {
    $original = new TenantSettings(refreshTokenTtl: 86400);
    $modified = $original->withRefreshTokenTtl(172800);

    $this->assertEquals(expected: 86400, actual: $original->refreshTokenTtl);
    $this->assertEquals(expected: 172800, actual: $modified->refreshTokenTtl);
  }

  /**
   * Method testToArray.
   *
   * Tests the toArray method.
   */
  #[Test]
  public function testToArray(): void
  {
    $settings = new TenantSettings(
      accessTokenTtl: 7200,
      refreshTokenTtl: 172800,
      requirePkce: true,
      allowPublicClients: false,
      allowedScopes: ['openid'],
      customIssuer: 'https://example.com',
    );

    $array = $settings->toArray();

    $this->assertEquals(expected: [
      'access_token_ttl' => 7200,
      'refresh_token_ttl' => 172800,
      'require_pkce' => true,
      'allow_public_clients' => false,
      'allowed_scopes' => ['openid'],
      'custom_issuer' => 'https://example.com',
    ], actual: $array);
  }

  /**
   * Method testFromArray.
   *
   * Tests the fromArray factory method.
   */
  #[Test]
  public function testFromArray(): void
  {
    $data = [
      'access_token_ttl' => 7200,
      'refresh_token_ttl' => 172800,
      'require_pkce' => false,
      'allow_public_clients' => true,
      'allowed_scopes' => ['openid', 'profile'],
      'custom_issuer' => 'https://custom.com',
    ];

    $settings = TenantSettings::fromArray($data);

    $this->assertEquals(expected: 7200, actual: $settings->accessTokenTtl);
    $this->assertEquals(expected: 172800, actual: $settings->refreshTokenTtl);
    $this->assertFalse(condition: $settings->requirePkce);
    $this->assertTrue(condition: $settings->allowPublicClients);
    $this->assertEquals(expected: ['openid', 'profile'], actual: $settings->allowedScopes);
    $this->assertEquals(expected: 'https://custom.com', actual: $settings->customIssuer);
  }

  /**
   * Method testFromArrayWithDefaults.
   *
   * Tests fromArray with missing values uses defaults.
   */
  #[Test]
  public function testFromArrayWithDefaults(): void
  {
    $settings = TenantSettings::fromArray([]);

    $this->assertEquals(expected: 3600, actual: $settings->accessTokenTtl);
    $this->assertEquals(expected: 86400, actual: $settings->refreshTokenTtl);
    $this->assertTrue(condition: $settings->requirePkce);
    $this->assertFalse(condition: $settings->allowPublicClients);
  }
  // #endregion
}
