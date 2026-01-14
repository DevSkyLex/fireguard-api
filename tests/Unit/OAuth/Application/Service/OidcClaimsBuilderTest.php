<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Application\Service;

use DateTimeImmutable;
use OAuth\Application\Service\OidcClaimsBuilder;
use OAuth\Domain\Model\Oidc\OidcUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test OidcClaimsBuilderTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OidcClaimsBuilder::class)]
final class OidcClaimsBuilderTest extends TestCase
{
  // #region Methods
  /**
   * Method testBuildUserInfoClaimsWithProfileAndEmailScopes.
   *
   * Ensures profile and email claims are included
   * and auth_time is omitted for userinfo.
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildUserInfoClaimsWithProfileAndEmailScopes(): void
  {
    $builder = new OidcClaimsBuilder();
    $user = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'jdoe',
      email: 'jdoe@example.com',
      emailVerified: true,
      givenName: 'John',
      familyName: 'Doe',
      pictureUrl: 'https://cdn.example.com/avatar.png',
      authTime: new DateTimeImmutable('@1700000000'),
    );

    $claims = $builder->buildUserInfoClaims($user, ['openid', 'profile', 'email']);

    self::assertSame('user-id', $claims['sub']);
    self::assertSame('John Doe', $claims['name']);
    self::assertSame('John', $claims['given_name']);
    self::assertSame('Doe', $claims['family_name']);
    self::assertSame('jdoe', $claims['preferred_username']);
    self::assertSame('https://cdn.example.com/avatar.png', $claims['picture']);
    self::assertSame('jdoe@example.com', $claims['email']);
    self::assertTrue($claims['email_verified']);
    self::assertArrayNotHasKey('auth_time', $claims);
  }

  /**
   * Method testBuildIdTokenClaimsIncludesAuthTime.
   *
   * Ensures auth_time is included for ID tokens.
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildIdTokenClaimsIncludesAuthTime(): void
  {
    $builder = new OidcClaimsBuilder();
    $authTime = new DateTimeImmutable('@1700000000');
    $user = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'jdoe',
      email: 'jdoe@example.com',
      emailVerified: true,
      givenName: 'John',
      familyName: 'Doe',
      pictureUrl: null,
      authTime: $authTime,
    );

    $claims = $builder->buildIdTokenClaims($user, ['openid', 'profile', 'email']);

    self::assertSame($authTime->getTimestamp(), $claims['auth_time']);
  }

  /**
   * Method testBuildClaimsSkipsOptionalScopes.
   *
   * Ensures optional claims are not returned
   * when the corresponding scopes are missing.
   *
   * @return void no return value
   */
  #[Test]
  public function testBuildClaimsSkipsOptionalScopes(): void
  {
    $builder = new OidcClaimsBuilder();
    $user = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'jdoe',
      email: 'jdoe@example.com',
      emailVerified: true,
      givenName: 'John',
      familyName: 'Doe',
      pictureUrl: 'https://cdn.example.com/avatar.png',
      authTime: null,
    );

    $claims = $builder->buildUserInfoClaims($user, ['openid']);

    self::assertSame('user-id', $claims['sub']);
    self::assertArrayNotHasKey('email', $claims);
    self::assertArrayNotHasKey('preferred_username', $claims);
    self::assertArrayNotHasKey('name', $claims);
  }
  // #endregion
}
