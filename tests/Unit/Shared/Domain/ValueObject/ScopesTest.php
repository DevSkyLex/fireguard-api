<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\Scope;
use Shared\Domain\ValueObject\Scopes;

/**
 * Test ScopesTest
 * @final
 *
 * Unit tests for the Scopes Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 * @version 1.0.0
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 * 
 * @covers \Shared\Domain\ValueObject\Scopes
 */
#[CoversClass(className: Scopes::class)]
final class ScopesTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedAndAccessed
   *
   * Tests that Scopes can be created with variadic parameters
   * and that the collection can be accessed and queried.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedAndAccessed(): void
  {
    $s1 = new Scope(value: 'read');
    $s2 = new Scope(value: 'write');
    $scopes = new Scopes($s1, $s2);

    $this->assertCount(expectedCount: 2, haystack: $scopes);
    $this->assertTrue(condition: $scopes->contains($s1));
    $this->assertTrue(condition: $scopes->contains($s2));
    $this->assertFalse(condition: $scopes->contains(new Scope('delete')));
  }

  /**
   * Method testIteration
   *
   * Tests that Scopes collection can be iterated over.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testIteration(): void
  {
    $s1 = new Scope(value: 'read');
    $scopes = new Scopes($s1);

    foreach ($scopes as $scope) {
      $this->assertTrue(condition: $scope->equals($s1));
    }
  }

  /**
   * Method testFromArray
   *
   * Tests that Scopes can be created from an array of strings.
   *
   * @access public
   * @since 1.0.0
   *
   * @return void No return value.
   */
  #[Test]
  public function testFromArray(): void
  {
    $scopes = Scopes::fromArray(['read', 'write']);

    $this->assertCount(expectedCount: 2, haystack: $scopes);
    $this->assertTrue(condition: $scopes->contains(new Scope('read')));
  }

  //#endregion
}

