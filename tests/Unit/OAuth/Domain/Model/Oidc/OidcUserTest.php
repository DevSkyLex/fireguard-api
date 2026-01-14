<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\Model\Oidc;

use DateTimeImmutable;
use OAuth\Domain\Model\Oidc\OidcUser;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test OidcUserTest.
 *
 * @category Domain Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: OidcUser::class)]
final class OidcUserTest extends TestCase
{
  // #region Methods
  /**
   * Method testConstructorRejectsEmptySubject.
   *
   * @return void no return value
   */
  #[Test]
  public function testConstructorRejectsEmptySubject(): void
  {
    $this->expectException(InvalidValueException::class);

    new OidcUser(subject: '  ');
  }

  /**
   * Method testGettersReturnValues.
   *
   * @return void no return value
   */
  #[Test]
  public function testGettersReturnValues(): void
  {
    $authTime = new DateTimeImmutable('@1700000000');

    $user = new OidcUser(
      subject: 'user-id',
      preferredUsername: 'jdoe',
      email: 'jdoe@example.com',
      emailVerified: true,
      givenName: 'John',
      familyName: 'Doe',
      pictureUrl: 'https://cdn.example.com/avatar.png',
      authTime: $authTime,
    );

    self::assertSame('user-id', $user->subject());
    self::assertSame('jdoe', $user->preferredUsername());
    self::assertSame('jdoe@example.com', $user->email());
    self::assertTrue($user->emailVerified());
    self::assertSame('John', $user->givenName());
    self::assertSame('Doe', $user->familyName());
    self::assertSame('https://cdn.example.com/avatar.png', $user->pictureUrl());
    self::assertSame($authTime, $user->authTime());
  }
  // #endregion
}
