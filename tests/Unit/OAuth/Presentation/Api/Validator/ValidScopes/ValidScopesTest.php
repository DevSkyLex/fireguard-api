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

  #[Test]
  public function testTheLegacyOptionsArrayIsHonoured(): void
  {
    $constraint = new ValidScopes(options: [
      'allowedScopes' => ['openid', 'admin'],
      'message' => 'Rejected {{ scope }}.',
      'groups' => ['registration'],
      'payload' => ['origin' => 'legacy'],
    ]);

    self::assertSame(['openid', 'admin'], $constraint->allowedScopes);
    self::assertSame('Rejected {{ scope }}.', $constraint->message);
    self::assertSame(['registration'], $constraint->groups);
    self::assertSame(['origin' => 'legacy'], $constraint->payload);
  }

  #[Test]
  public function testAMalformedAllowListOptionLeavesTheDefaultIntact(): void
  {
    $default = new ValidScopes()->allowedScopes;

    self::assertSame(
      $default,
      new ValidScopes(options: ['allowedScopes' => ['openid', 42]])->allowedScopes,
      'A non-string entry must not be able to widen the scope allow-list.',
    );
  }

  #[Test]
  public function testAnAssociativeAllowListOptionIsIgnored(): void
  {
    $default = new ValidScopes()->allowedScopes;

    self::assertSame(
      $default,
      new ValidScopes(options: ['allowedScopes' => ['first' => 'openid']])->allowedScopes,
    );
  }

  #[Test]
  public function testANonArrayAllowListOptionIsIgnored(): void
  {
    $default = new ValidScopes()->allowedScopes;

    self::assertSame($default, new ValidScopes(options: ['allowedScopes' => 'openid'])->allowedScopes);
  }

  #[Test]
  public function testAMalformedMessageOptionLeavesTheDefaultIntact(): void
  {
    $default = new ValidScopes()->message;

    self::assertSame($default, new ValidScopes(options: ['message' => 123])->message);
  }

  #[Test]
  public function testAMalformedGroupsOptionIsIgnored(): void
  {
    $constraint = new ValidScopes(options: ['groups' => ['registration', 7]]);

    self::assertSame(['Default'], $constraint->groups);
  }

  #[Test]
  public function testAnExplicitArgumentWinsOverTheOptionsArray(): void
  {
    $constraint = new ValidScopes(
      allowedScopes: ['openid'],
      message: 'Explicit.',
      options: ['allowedScopes' => ['admin'], 'message' => 'From options.'],
    );

    self::assertSame(['openid'], $constraint->allowedScopes);
    self::assertSame('Explicit.', $constraint->message);
  }
  // #endregion
}
