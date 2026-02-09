<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Presentation\Api\Validator\GrantType;

use Auth\Domain\Exception\Session\ValidationException;
use Auth\Presentation\Api\Validator\GrantType\GrantTypeValidator;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GrantTypeValidatorTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: GrantTypeValidator::class)]
final class GrantTypeValidatorTest extends TestCase
{
  // #region Methods
  /**
   * Method testValidateRejectsUnsupportedGrantType.
   */
  #[Test]
  public function testValidateRejectsUnsupportedGrantType(): void
  {
    $validator = new GrantTypeValidator();

    $this->expectException(ValidationException::class);
    $validator->validate('password');
  }

  /**
   * Method testValidateRefreshTokenRequiresToken.
   */
  #[Test]
  public function testValidateRefreshTokenRequiresToken(): void
  {
    $validator = new GrantTypeValidator();

    $this->expectException(ValidationException::class);
    $validator->validate(GrantTypeValidator::GRANT_REFRESH_TOKEN, null);
  }

  /**
   * Method testValidateAuthorizationCodeRequiresFields.
   */
  #[Test]
  public function testValidateAuthorizationCodeRequiresFields(): void
  {
    $validator = new GrantTypeValidator();

    $this->expectException(ValidationException::class);
    $validator->validate(GrantTypeValidator::GRANT_AUTHORIZATION_CODE, null, null, null, null);
  }

  #[Test]
  public function testValidateAuthorizationCodeRequiresRedirectUri(): void
  {
    $validator = new GrantTypeValidator();

    $this->expectException(ValidationException::class);
    $validator->validate(
      grantType: GrantTypeValidator::GRANT_AUTHORIZATION_CODE,
      refreshToken: null,
      code: 'code-123',
      redirectUri: null,
      codeVerifier: 'verifier',
    );
  }

  #[Test]
  public function testValidateAuthorizationCodeRequiresCodeVerifier(): void
  {
    $validator = new GrantTypeValidator();

    $this->expectException(ValidationException::class);
    $validator->validate(
      grantType: GrantTypeValidator::GRANT_AUTHORIZATION_CODE,
      refreshToken: null,
      code: 'code-123',
      redirectUri: 'https://client.example.com/callback',
      codeVerifier: null,
    );
  }

  /**
   * Method testValidateAuthorizationCodeSucceedsWhenValid.
   */
  #[Test]
  public function testValidateAuthorizationCodeSucceedsWhenValid(): void
  {
    $validator = new GrantTypeValidator();

    $validator->validate(
      grantType: GrantTypeValidator::GRANT_AUTHORIZATION_CODE,
      refreshToken: null,
      code: 'code-123',
      redirectUri: 'https://client.example.com/callback',
      codeVerifier: 'verifier',
    );

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testValidateRefreshTokenSucceedsWhenTokenProvided(): void
  {
    $validator = new GrantTypeValidator();

    $validator->validate(
      grantType: GrantTypeValidator::GRANT_REFRESH_TOKEN,
      refreshToken: 'refresh-token',
    );

    $this->addToAssertionCount(1);
  }

  #[Test]
  public function testValidateClientCredentialsSucceeds(): void
  {
    $validator = new GrantTypeValidator();

    $validator->validate(GrantTypeValidator::GRANT_CLIENT_CREDENTIALS);

    $this->addToAssertionCount(1);
  }
  // #endregion
}
