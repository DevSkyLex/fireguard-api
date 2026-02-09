<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Token;

use InvalidArgumentException;
use OAuth\Domain\ValueObject\Token\TokenIdentifier;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function strlen;

/**
 * Test TokenIdentifierTest.
 *
 * @category Value Object Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(TokenIdentifier::class)]
final class TokenIdentifierTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testGenerateCreatesIdentifier(): void
  {
    $identifier = TokenIdentifier::generate(10);

    self::assertNotSame('', $identifier->value);
    self::assertSame(20, strlen($identifier->value));
  }

  #[Test]
  public function testEqualsMatchesValue(): void
  {
    $a = new TokenIdentifier('abc');
    $b = new TokenIdentifier('abc');

    self::assertTrue($a->equals($b));
  }

  #[Test]
  public function testEmptyValueThrowsException(): void
  {
    $this->expectException(InvalidArgumentException::class);

    new TokenIdentifier('');
  }

  #[Test]
  public function testToStringReturnsValue(): void
  {
    $identifier = new TokenIdentifier('token-123');

    self::assertSame('token-123', (string) $identifier);
  }
  // #endregion
}
