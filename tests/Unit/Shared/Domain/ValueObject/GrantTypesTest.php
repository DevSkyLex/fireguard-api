<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use Shared\Domain\ValueObject\GrantType;
use Shared\Domain\ValueObject\GrantTypes;

/**
 * Test GrantTypesTest
 * @final
 *
 * Unit tests for the GrantTypes Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\GrantTypes
 */
final class GrantTypesTest extends TestCase
{
  /**
   * Method testCanBeCreatedAndAccessed
   *
   * Tests that GrantTypes can be created with variadic parameters
   * and that the collection can be accessed and queried.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testCanBeCreatedAndAccessed(): void
  {
    $g1 = new GrantType('authorization_code');
    $g2 = new GrantType('refresh_token');
    $grantTypes = new GrantTypes($g1, $g2);

    $this->assertCount(2, $grantTypes);
    $this->assertTrue($grantTypes->contains($g1));
    $this->assertTrue($grantTypes->contains($g2));
    $this->assertFalse($grantTypes->contains(new GrantType('password')));
  }

  /**
   * Method testIteration
   *
   * Tests that GrantTypes collection can be iterated over.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testIteration(): void
  {
    $g1 = new GrantType('authorization_code');
    $grantTypes = new GrantTypes($g1);

    foreach ($grantTypes as $grantType) {
      $this->assertTrue($grantType->equals($g1));
    }
  }

  /**
   * Method testFromArray
   *
   * Tests that GrantTypes can be created from an array of strings.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  public function testFromArray(): void
  {
    $grantTypes = GrantTypes::fromArray(['authorization_code', 'refresh_token']);

    $this->assertCount(2, $grantTypes);
    $this->assertTrue($grantTypes->contains(new GrantType('authorization_code')));
  }
}
