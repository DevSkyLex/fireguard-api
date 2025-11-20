<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\HashedSecret;

/**
 * Test HashedSecretTest
 * @final
 *
 * Unit tests for the HashedSecret Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\HashedSecret
 */
final class HashedSecretTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithValidValue
   *
   * Tests that a valid HashedSecret can be 
   * created with a bcrypt hash.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testCanBeCreatedWithValidValue(): void
  {
    $value = '$2y$10$abcdefghijklmnopqrstuv';
    $secret = new HashedSecret(value: $value);

    $this->assertEquals(expected: $value, actual: $secret->value);
    $this->assertEquals(expected: $value, actual: (string) $secret);
  }

  /**
   * Method testEquality
   *
   * Tests equality comparison between 
   * HashedSecret objects.
   *
   * @access public
   *
   * @return void No return value.
   */
  public function testEquality(): void
  {
    $s1 = new HashedSecret(value: '$2y$10$abcdefghijklmnopqrstuv');
    $s2 = new HashedSecret(value: '$2y$10$abcdefghijklmnopqrstuv');
    $s3 = new HashedSecret(value: '$2y$10$zyxwvutsrqponmlkjihgfe');

    $this->assertTrue(condition: $s1->equals($s2));
    $this->assertFalse(condition: $s1->equals($s3));
  }
  //#endregion
}
