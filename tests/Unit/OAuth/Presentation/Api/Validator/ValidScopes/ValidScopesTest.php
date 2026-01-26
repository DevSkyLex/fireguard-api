<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Presentation\Api\Validator\ValidScopes;

use OAuth\Presentation\Api\Validator\ValidScopes\ValidScopes;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ValidScopesTest.
 *
 * @category Validator Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ValidScopes::class)]
final class ValidScopesTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testDefaultsAreConfigured(): void
  {
    $constraint = new ValidScopes();

    self::assertSame(
      'The scope "{{ scope }}" is not a valid OAuth2 scope.',
      $constraint->message,
    );
    self::assertContains('openid', $constraint->allowedScopes);
  }

  #[Test]
  public function testOverridesAllowedScopesAndMessage(): void
  {
    $constraint = new ValidScopes(
      allowedScopes: ['custom'],
      message: 'Invalid scope.',
    );

    self::assertSame(['custom'], $constraint->allowedScopes);
    self::assertSame('Invalid scope.', $constraint->message);
  }
  // #endregion
}
