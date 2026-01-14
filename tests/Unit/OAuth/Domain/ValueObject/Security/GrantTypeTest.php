<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Security;

use OAuth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use ValueError;

/**
 * Class GrantTypeTest.
 *
 * Unit tests for the GrantType Enum (OAuth 2.1 compliant).
 * Note: PASSWORD and IMPLICIT grants have been removed.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\ValueObject\Security\GrantType
 */
#[CoversClass(className: GrantType::class)]
final class GrantTypeTest extends TestCase
{
  // #region Methods
  /**
   * Method testEnumCases.
   *
   * Tests that all expected enum cases exist.
   *
   * @return void no return value
   */
  #[Test]
  public function testEnumCases(): void
  {
    $cases = GrantType::cases();

    $this->assertCount(3, $cases);
    $this->assertContains(GrantType::AUTHORIZATION_CODE, $cases);
    $this->assertContains(GrantType::CLIENT_CREDENTIALS, $cases);
    $this->assertContains(GrantType::REFRESH_TOKEN, $cases);
  }

  /**
   * Method testEnumValues.
   *
   * Tests that enum values match expected strings.
   *
   * @return void no return value
   */
  #[Test]
  public function testEnumValues(): void
  {
    $this->assertEquals('AUTHORIZATION_CODE', GrantType::AUTHORIZATION_CODE->value);
    $this->assertEquals('CLIENT_CREDENTIALS', GrantType::CLIENT_CREDENTIALS->value);
    $this->assertEquals('REFRESH_TOKEN', GrantType::REFRESH_TOKEN->value);
  }

  /**
   * Method testFromString.
   *
   * Tests creating enum from string value.
   *
   * @return void no return value
   */
  #[Test]
  public function testFromString(): void
  {
    $grantType = GrantType::from('AUTHORIZATION_CODE');
    $this->assertSame(GrantType::AUTHORIZATION_CODE, $grantType);
  }

  /**
   * Method testFromInvalidStringThrowsException.
   *
   * Tests that creating enum from invalid string throws ValueError.
   *
   * @return void no return value
   */
  #[Test]
  public function testFromInvalidStringThrowsException(): void
  {
    $this->expectException(ValueError::class);
    GrantType::from('invalid_grant_type');
  }

  // /**
  //  * Method testTryFromReturnsNullForInvalidValue
  //  *
  //  * Tests that tryFrom returns null for invalid values.
  //  *
  //  * @access public
  //  *
  //  * @return void No return value.
  //  */
  // public function testTryFromReturnsNullForInvalidValue(): void
  // {
  //   $result = GrantType::tryFrom('invalid_grant_type');
  //   $this->assertNull($result);
  // }

  /**
   * Method testIsAuthorizationCode.
   *
   * Tests the isAuthorizationCode method.
   *
   * @return void no return value
   */
  #[Test]
  public function testIsAuthorizationCode(): void
  {
    $this->assertTrue(GrantType::AUTHORIZATION_CODE->isAuthorizationCode());
    $this->assertFalse(GrantType::CLIENT_CREDENTIALS->isAuthorizationCode());
  }

  /**
   * Method testIsClientCredentials.
   *
   * Tests the isClientCredentials method.
   *
   * @return void no return value
   */
  #[Test]
  public function testIsClientCredentials(): void
  {
    $this->assertTrue(GrantType::CLIENT_CREDENTIALS->isClientCredentials());
    $this->assertFalse(GrantType::AUTHORIZATION_CODE->isClientCredentials());
  }

  /**
   * Method testIsRefreshToken.
   *
   * Tests the isRefreshToken method.
   *
   * @return void no return value
   */
  #[Test]
  public function testIsRefreshToken(): void
  {
    $this->assertTrue(GrantType::REFRESH_TOKEN->isRefreshToken());
    $this->assertFalse(GrantType::CLIENT_CREDENTIALS->isRefreshToken());
  }

  /**
   * Method testRequiresUserAuthentication.
   *
   * Tests the requiresUserAuthentication method.
   *
   * @return void no return value
   */
  #[Test]
  public function testRequiresUserAuthentication(): void
  {
    // Grant types that require user authentication
    $this->assertTrue(GrantType::AUTHORIZATION_CODE->requiresUserAuthentication());

    // Grant types that don't require user authentication
    $this->assertFalse(GrantType::CLIENT_CREDENTIALS->requiresUserAuthentication());
    $this->assertFalse(GrantType::REFRESH_TOKEN->requiresUserAuthentication());
  }

  /**
   * Method testLabel.
   *
   * Tests the label method returns human-readable labels.
   *
   * @return void no return value
   */
  #[Test]
  public function testLabel(): void
  {
    $this->assertEquals('Authorization Code', GrantType::AUTHORIZATION_CODE->label());
    $this->assertEquals('Client Credentials', GrantType::CLIENT_CREDENTIALS->label());
    $this->assertEquals('Refresh Token', GrantType::REFRESH_TOKEN->label());
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between GrantType enums.
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $g1 = GrantType::CLIENT_CREDENTIALS;
    $g2 = GrantType::from('CLIENT_CREDENTIALS');
    $g3 = GrantType::AUTHORIZATION_CODE;

    $this->assertSame($g1, $g2);
    $this->assertNotSame($g1, $g3);
  }
  // #endregion
}
