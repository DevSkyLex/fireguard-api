<?php

declare(strict_types=1);

namespace Tests\Unit\Shared\Domain\ValueObject;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\RateLimitResult;

/**
 * Class RateLimitResultTest
 *
 * Unit tests for the RateLimitResult Value Object.
 *
 * @category Unit Test
 * @package Tests\Unit\Shared\Domain\ValueObject
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Shared\Domain\ValueObject\RateLimitResult
 */
#[CoversClass(className: RateLimitResult::class)]
final class RateLimitResultTest extends TestCase
{
  //#region Methods
  /**
   * Method testCanBeCreatedWithAcceptedStatus
   *
   * Tests that an accepted RateLimitResult can be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedWithAcceptedStatus(): void
  {
    $result = new RateLimitResult(
      accepted: true,
      remainingTokens: 5,
      retryAfter: 0,
    );

    $this->assertTrue(condition: $result->accepted);
    $this->assertEquals(expected: 5, actual: $result->remainingTokens);
    $this->assertEquals(expected: 0, actual: $result->retryAfter);
  }

  /**
   * Method testCanBeCreatedWithRejectedStatus
   *
   * Tests that a rejected RateLimitResult can be created.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testCanBeCreatedWithRejectedStatus(): void
  {
    $result = new RateLimitResult(
      accepted: false,
      remainingTokens: 0,
      retryAfter: 60,
    );

    $this->assertFalse(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
    $this->assertEquals(expected: 60, actual: $result->retryAfter);
  }

  /**
   * Method testAcceptedFactoryMethod
   *
   * Tests the accepted factory method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testAcceptedFactoryMethod(): void
  {
    $result = RateLimitResult::accepted(remainingTokens: 10);

    $this->assertTrue(condition: $result->accepted);
    $this->assertEquals(expected: 10, actual: $result->remainingTokens);
    $this->assertEquals(expected: 0, actual: $result->retryAfter);
  }

  /**
   * Method testAcceptedFactoryMethodWithDefaultTokens
   *
   * Tests the accepted factory method with default tokens.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testAcceptedFactoryMethodWithDefaultTokens(): void
  {
    $result = RateLimitResult::accepted();

    $this->assertTrue(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
  }

  /**
   * Method testRejectedFactoryMethod
   *
   * Tests the rejected factory method.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testRejectedFactoryMethod(): void
  {
    $result = RateLimitResult::rejected(retryAfter: 120);

    $this->assertFalse(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
    $this->assertEquals(expected: 120, actual: $result->retryAfter);
  }

  /**
   * Method testDefaultValues
   *
   * Tests default values for constructor parameters.
   *
   * @access public
   *
   * @return void No return value.
   */
  #[Test]
  public function testDefaultValues(): void
  {
    $result = new RateLimitResult(accepted: true);

    $this->assertTrue(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
    $this->assertEquals(expected: 0, actual: $result->retryAfter);
  }
  //#endregion
}
