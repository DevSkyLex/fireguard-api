<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\TokenClaims;

/**
 * Test TokenClaimsTest
 * @final
 *
 * Unit tests for the TokenClaims Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\TokenClaims
 */
final class TokenClaimsTest extends TestCase
{
  /**
   * Method testCanBeCreatedAndAccessed
   *
   * Tests that TokenClaims can be created with an array of claims
   * and that individual claims can be accessed and queried.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCanBeCreatedAndAccessed(): void
  {
    $claims = [
      'sub' => 'user-1',
      'exp' => 1234567890,
    ];
    $tokenClaims = new TokenClaims($claims);

    $this->assertEquals($claims, $tokenClaims->toArray());
    $this->assertTrue($tokenClaims->has('sub'));
    $this->assertEquals('user-1', $tokenClaims->get('sub'));
    $this->assertFalse($tokenClaims->has('iss'));
    $this->assertNull($tokenClaims->get('iss'));
  }

  /**
   * Method testJsonSerialization
   *
   * Tests that TokenClaims can be JSON serialized.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testJsonSerialization(): void
  {
    $claims = ['sub' => 'user-1'];
    $tokenClaims = new TokenClaims($claims);

    $this->assertEquals($claims, $tokenClaims->jsonSerialize());
  }
}

