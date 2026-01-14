<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Domain\ValueObject\Security;

use Auth\Domain\ValueObject\Security\GrantType;
use PHPUnit\Framework\Attributes\{CoversClass, DataProvider, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GrantTypeTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GrantType::class)]
final class GrantTypeTest extends TestCase
{
  // #region Methods
  /**
   * Method testValuesReturnsAllCases.
   *
   * Tests that values returns all enum values.
   */
  #[Test]
  public function testValuesReturnsAllCases(): void
  {
    $this->assertSame(
      [GrantType::AUTHORIZATION_CODE->value, GrantType::CLIENT_CREDENTIALS->value, GrantType::REFRESH_TOKEN->value],
      GrantType::values(),
    );
  }

  /**
   * Method testHelperMethods.
   *
   * Tests helper methods and labels per grant type.
   */
  #[Test]
  #[DataProvider('grantTypeProvider')]
  public function testHelperMethods(
    GrantType $type,
    bool $authorizationCode,
    bool $clientCredentials,
    bool $refreshToken,
    bool $requiresUser,
    string $label,
  ): void {
    $this->assertSame($authorizationCode, $type->isAuthorizationCode());
    $this->assertSame($clientCredentials, $type->isClientCredentials());
    $this->assertSame($refreshToken, $type->isRefreshToken());
    $this->assertSame($requiresUser, $type->requiresUserAuthentication());
    $this->assertSame($label, $type->label());
  }

  /**
   * Method grantTypeProvider.
   *
   * @return array<string, array{GrantType, bool, bool, bool, bool, string}>
   */
  public static function grantTypeProvider(): array
  {
    return [
      'authorization code' => [GrantType::AUTHORIZATION_CODE, true, false, false, true, 'Authorization Code'],
      'client credentials' => [GrantType::CLIENT_CREDENTIALS, false, true, false, false, 'Client Credentials'],
      'refresh token' => [GrantType::REFRESH_TOKEN, false, false, true, false, 'Refresh Token'],
    ];
  }
  // #endregion
}
