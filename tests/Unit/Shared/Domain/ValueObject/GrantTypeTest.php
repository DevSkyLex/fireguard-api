<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\GrantType;

/**
 * Class GrantTypeTest
 *
 * Unit tests for the GrantType Enum.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * @covers \Shared\Domain\ValueObject\GrantType
 */
#[CoversClass(className: GrantType::class)]
final class GrantTypeTest extends TestCase
{
  //#region Methods
  /**
   * Method testEnumCases
   *
   * Tests that all expected enum cases exist.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testEnumCases(): void
  {
    $cases = GrantType::cases();

    $this->assertCount(5, $cases);
    $this->assertContains(GrantType::AUTHORIZATION_CODE, $cases);
    $this->assertContains(GrantType::CLIENT_CREDENTIALS, $cases);
    $this->assertContains(GrantType::REFRESH_TOKEN, $cases);
    $this->assertContains(GrantType::PASSWORD, $cases);
    $this->assertContains(GrantType::IMPLICIT, $cases);
  }

  /**
   * Method testEnumValues
   *
   * Tests that enum values match expected strings.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testEnumValues(): void
  {
    $this->assertEquals('authorization_code', GrantType::AUTHORIZATION_CODE->value);
    $this->assertEquals('client_credentials', GrantType::CLIENT_CREDENTIALS->value);
    $this->assertEquals('refresh_token', GrantType::REFRESH_TOKEN->value);
    $this->assertEquals('password', GrantType::PASSWORD->value);
    $this->assertEquals('implicit', GrantType::IMPLICIT->value);
  }

  /**
   * Method testFromString
   *
   * Tests creating enum from string value.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testFromString(): void
  {
    $grantType = GrantType::from('authorization_code');
    $this->assertSame(GrantType::AUTHORIZATION_CODE, $grantType);
  }

  /**
   * Method testFromInvalidStringThrowsException
   *
   * Tests that creating enum from invalid string throws ValueError.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testFromInvalidStringThrowsException(): void
  {
    $this->expectException(\ValueError::class);
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
   * Method testIsAuthorizationCode
   *
   * Tests the isAuthorizationCode method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testIsAuthorizationCode(): void
  {
    $this->assertTrue(GrantType::AUTHORIZATION_CODE->isAuthorizationCode());
    $this->assertFalse(GrantType::CLIENT_CREDENTIALS->isAuthorizationCode());
  }

  /**
   * Method testIsClientCredentials
   *
   * Tests the isClientCredentials method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testIsClientCredentials(): void
  {
    $this->assertTrue(GrantType::CLIENT_CREDENTIALS->isClientCredentials());
    $this->assertFalse(GrantType::AUTHORIZATION_CODE->isClientCredentials());
  }

  /**
   * Method testIsRefreshToken
   *
   * Tests the isRefreshToken method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testIsRefreshToken(): void
  {
    $this->assertTrue(GrantType::REFRESH_TOKEN->isRefreshToken());
    $this->assertFalse(GrantType::PASSWORD->isRefreshToken());
  }

  /**
   * Method testRequiresUserAuthentication
   *
   * Tests the requiresUserAuthentication method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testRequiresUserAuthentication(): void
  {
    // Grant types that require user authentication
    $this->assertTrue(GrantType::AUTHORIZATION_CODE->requiresUserAuthentication());
    $this->assertTrue(GrantType::PASSWORD->requiresUserAuthentication());
    $this->assertTrue(GrantType::IMPLICIT->requiresUserAuthentication());

    // Grant types that don't require user authentication
    $this->assertFalse(GrantType::CLIENT_CREDENTIALS->requiresUserAuthentication());
    $this->assertFalse(GrantType::REFRESH_TOKEN->requiresUserAuthentication());
  }

  /**
   * Method testLabel
   *
   * Tests the label method returns human-readable labels.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testLabel(): void
  {
    $this->assertEquals('Authorization Code', GrantType::AUTHORIZATION_CODE->label());
    $this->assertEquals('Client Credentials', GrantType::CLIENT_CREDENTIALS->label());
    $this->assertEquals('Refresh Token', GrantType::REFRESH_TOKEN->label());
    $this->assertEquals('Password', GrantType::PASSWORD->label());
    $this->assertEquals('Implicit (Deprecated)', GrantType::IMPLICIT->label());
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between GrantType enums.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testEquality(): void
  {
    $g1 = GrantType::CLIENT_CREDENTIALS;
    $g2 = GrantType::from('client_credentials');
    $g3 = GrantType::PASSWORD;

    $this->assertSame($g1, $g2);
    $this->assertNotSame($g1, $g3);
  }
  //#endregion
}
