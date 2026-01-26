<?php

declare(strict_types=1);

namespace Tests\Unit\OAuth\Domain\ValueObject\Security;

use OAuth\Domain\ValueObject\Security\{GrantType, GrantTypes};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;
use ValueError;

/**
 * Test GrantTypesTest.
 *
 * @category Unit Test
 *
 * @version 2.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \OAuth\Domain\ValueObject\Security\GrantTypes
 */
#[CoversClass(className: GrantTypes::class)]
final class GrantTypesTest extends TestCase
{
  /**
   * Method testCanBeCreatedAndAccessed.
   *
   * Tests that GrantTypes can be created with variadic parameters
   * and that the collection can be accessed and queried.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testCanBeCreatedAndAccessed(): void
  {
    $g1 = GrantType::AUTHORIZATION_CODE;
    $g2 = GrantType::REFRESH_TOKEN;
    $grantTypes = new GrantTypes($g1, $g2);

    $this->assertCount(2, $grantTypes);
    $this->assertTrue($grantTypes->contains($g1));
    $this->assertTrue($grantTypes->contains($g2));
    $this->assertFalse($grantTypes->contains(GrantType::CLIENT_CREDENTIALS));
  }

  /**
   * Method testIteration.
   *
   * Tests that GrantTypes collection can be iterated over.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testIteration(): void
  {
    $g1 = GrantType::AUTHORIZATION_CODE;
    $grantTypes = new GrantTypes($g1);

    foreach ($grantTypes as $grantType) {
      $this->assertSame($g1, $grantType);
    }
  }

  /**
   * Method testFromArray.
   *
   * Tests that GrantTypes can be created from an array of strings.
   *
   * @since 1.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testFromArray(): void
  {
    $grantTypes = GrantTypes::fromArray(['AUTHORIZATION_CODE', 'REFRESH_TOKEN']);

    $this->assertCount(2, $grantTypes);
    $this->assertTrue($grantTypes->contains(GrantType::AUTHORIZATION_CODE));
  }

  /**
   * Method testRemovesDuplicates.
   *
   * Tests that duplicate grant types are removed.
   *
   * @since 2.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testRemovesDuplicates(): void
  {
    $grantTypes = new GrantTypes(
      GrantType::AUTHORIZATION_CODE,
      GrantType::AUTHORIZATION_CODE,
      GrantType::CLIENT_CREDENTIALS,
    );

    $this->assertCount(2, $grantTypes);
  }

  /**
   * Method testToArray.
   *
   * Tests that GrantTypes can be converted to an array of strings.
   *
   * @since 2.0.0
   *
   * @return void no return value
   */
  #[Test]
  public function testToArray(): void
  {
    $grantTypes = new GrantTypes(
      GrantType::AUTHORIZATION_CODE,
      GrantType::CLIENT_CREDENTIALS,
    );

    $array = $grantTypes->toArray();

    $this->assertEquals(['AUTHORIZATION_CODE', 'CLIENT_CREDENTIALS'], $array);
  }

  /**
   * Method testConstructorThrowsWhenEmpty.
   *
   * Tests that at least one grant type is required.
   *
   * @since 2.0.0
   */
  #[Test]
  public function testConstructorThrowsWhenEmpty(): void
  {
    $this->expectException(InvalidValueException::class);

    new GrantTypes();
  }

  /**
   * Method testFromArrayThrowsWhenEmpty.
   *
   * Tests that an empty array is rejected.
   *
   * @since 2.0.0
   */
  #[Test]
  public function testFromArrayThrowsWhenEmpty(): void
  {
    $this->expectException(InvalidValueException::class);

    GrantTypes::fromArray([]);
  }

  /**
   * Method testFromArrayThrowsWhenInvalidValue.
   *
   * Tests that invalid grant type values throw.
   *
   * @since 2.0.0
   */
  #[Test]
  public function testFromArrayThrowsWhenInvalidValue(): void
  {
    $this->expectException(ValueError::class);

    GrantTypes::fromArray(['invalid']);
  }
}
