<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Scope;

use OAuth\Domain\ValueObject\Scope\{Scope, Scopes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ScopesTest.
 *
 * @category ValueObject Tests
 */
#[CoversClass(className: Scopes::class)]
final class ScopesTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testConstructorThrowsOnEmpty(): void
  {
    $this->expectException(InvalidValueException::class);

    new Scopes();
  }

  #[Test]
  public function testConstructorRemovesDuplicates(): void
  {
    $scopes = new Scopes(Scope::OPENID, Scope::OPENID, Scope::EMAIL);

    self::assertSame(2, $scopes->count());
  }

  #[Test]
  public function testFromArrayRemovesDuplicatesAndToString(): void
  {
    $scopes = Scopes::fromArray(['OPENID', 'EMAIL', 'OPENID']);

    self::assertSame(2, $scopes->count());
    self::assertTrue($scopes->contains(Scope::OPENID));
    self::assertSame('OPENID EMAIL', $scopes->toString());
  }

  #[Test]
  public function testFromStringParsesScopes(): void
  {
    $scopes = Scopes::fromString('OPENID PROFILE');

    self::assertSame(['OPENID', 'PROFILE'], $scopes->toArray());
  }

  #[Test]
  public function testFromArrayThrowsOnInvalidScope(): void
  {
    $this->expectException(InvalidValueException::class);

    Scopes::fromArray(['INVALID']);
  }

  #[Test]
  public function testFromArrayThrowsOnEmpty(): void
  {
    $this->expectException(InvalidValueException::class);

    Scopes::fromArray([]);
  }

  #[Test]
  public function testFromStringThrowsOnEmpty(): void
  {
    $this->expectException(InvalidValueException::class);

    Scopes::fromString('   ');
  }

  #[Test]
  public function testFromStringThrowsOnInvalidScope(): void
  {
    $this->expectException(InvalidValueException::class);

    Scopes::fromString('OPENID INVALID');
  }

  #[Test]
  public function testContainsReturnsFalseWhenMissing(): void
  {
    $scopes = Scopes::fromArray(['OPENID']);

    self::assertFalse($scopes->contains(Scope::EMAIL));
  }

  #[Test]
  public function testIteratorProvidesScopes(): void
  {
    $scopes = Scopes::fromArray(['OPENID', 'EMAIL']);

    $values = [];
    foreach ($scopes as $scope) {
      $values[] = $scope->value;
    }

    self::assertSame(['OPENID', 'EMAIL'], $values);
  }
  // #endregion
}
