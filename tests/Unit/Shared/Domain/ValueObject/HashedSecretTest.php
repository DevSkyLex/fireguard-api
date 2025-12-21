<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\HashedSecret;

/**
 * Test HashedSecretTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\HashedSecret
 */
#[CoversClass(className: HashedSecret::class)]
final class HashedSecretTest extends TestCase
{
  // #region Methods
  /**
   * Method testCanBeCreatedWithValidValue.
   *
   * Tests that a valid HashedSecret can be
   * created with a bcrypt hash.
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = '$2y$10$abcdefghijklmnopqrstuv';
    $secret = new HashedSecret(value: $value);

    $this->assertEquals(expected: $value, actual: $secret->value);
    $this->assertEquals(expected: $value, actual: (string) $secret);
  }

  /**
   * Method testEquality.
   *
   * Tests equality comparison between
   * HashedSecret objects.
   *
   * @return void no return value
   */
  #[Test]
  public function testEquality(): void
  {
    $s1 = new HashedSecret(value: '$2y$10$abcdefghijklmnopqrstuv');
    $s2 = new HashedSecret(value: '$2y$10$abcdefghijklmnopqrstuv');
    $s3 = new HashedSecret(value: '$2y$10$zyxwvutsrqponmlkjihgfe');

    $this->assertTrue(condition: $s1->equals($s2));
    $this->assertFalse(condition: $s1->equals($s3));
  }
  // #endregion
}
