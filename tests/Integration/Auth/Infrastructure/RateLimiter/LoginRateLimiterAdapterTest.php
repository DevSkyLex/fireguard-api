<?php

declare(strict_types=1);

namespace Tests\Integration\Auth\Infrastructure\RateLimiter;

use Auth\Infrastructure\RateLimiter\LoginRateLimiterAdapter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Shared\Domain\ValueObject\RateLimitResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

/**
 * Class LoginRateLimiterAdapterTest
 *
 * Integration tests for the LoginRateLimiterAdapter.
 *
 * @category Integration Test
 * @package Tests\Integration\Auth\Infrastructure\RateLimiter
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Infrastructure\RateLimiter\LoginRateLimiterAdapter
 */
#[CoversClass(className: LoginRateLimiterAdapter::class)]
final class LoginRateLimiterAdapterTest extends KernelTestCase
{
  //#region Properties
  private LoginRateLimiterAdapter $adapter;
  private string $testKey;
  //#endregion

  //#region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var RateLimiterFactory $loginLimiter */
    $loginLimiter = $container->get('limiter.login');
    $this->adapter = new LoginRateLimiterAdapter(loginLimiter: $loginLimiter);

    // Use unique key per test to avoid interference
    $this->testKey = 'test_' . uniqid();
  }

  protected function tearDown(): void
  {
    // Reset the limiter after each test
    $this->adapter->reset(key: $this->testKey);

    // Ensure kernel is properly shut down to avoid class redeclaration issues
    self::ensureKernelShutdown();

    parent::tearDown();
  }
  //#endregion

  //#region Consume Tests
  /**
   * Method testConsumeReturnsAcceptedOnFirstAttempt
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testConsumeReturnsAcceptedOnFirstAttempt(): void
  {
    $result = $this->adapter->consume(key: $this->testKey);

    $this->assertInstanceOf(expected: RateLimitResult::class, actual: $result);
    $this->assertTrue(condition: $result->accepted);
    $this->assertGreaterThan(0, $result->remainingTokens);
  }

  /**
   * Method testConsumeDecreasesRemainingTokens
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testConsumeDecreasesRemainingTokens(): void
  {
    $result1 = $this->adapter->consume(key: $this->testKey);
    $result2 = $this->adapter->consume(key: $this->testKey);

    $this->assertTrue(condition: $result1->accepted);
    $this->assertTrue(condition: $result2->accepted);
    $this->assertLessThan($result1->remainingTokens, $result2->remainingTokens);
  }

  /**
   * Method testConsumeReturnsRejectedWhenLimitExceeded
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testConsumeReturnsRejectedWhenLimitExceeded(): void
  {
    // Consume all tokens (limit is 5 per minute based on config)
    for ($i = 0; $i < 5; $i++) {
      $this->adapter->consume(key: $this->testKey);
    }

    // Next attempt should be rejected
    $result = $this->adapter->consume(key: $this->testKey);

    $this->assertFalse(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
    $this->assertGreaterThan(0, $result->retryAfter);
  }

  /**
   * Method testConsumeWithMultipleTokens
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testConsumeWithMultipleTokens(): void
  {
    $result = $this->adapter->consume(key: $this->testKey, tokens: 3);

    $this->assertTrue(condition: $result->accepted);
    // Should have 2 remaining (5 - 3 = 2)
    $this->assertEquals(expected: 2, actual: $result->remainingTokens);
  }

  /**
   * Method testDifferentKeysAreIndependent
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testDifferentKeysAreIndependent(): void
  {
    $key1 = $this->testKey . '_user1';
    $key2 = $this->testKey . '_user2';

    // Exhaust limit for key1
    for ($i = 0; $i < 5; $i++) {
      $this->adapter->consume(key: $key1);
    }

    // key2 should still be accepted
    $result = $this->adapter->consume(key: $key2);

    $this->assertTrue(condition: $result->accepted);

    // Cleanup
    $this->adapter->reset(key: $key1);
    $this->adapter->reset(key: $key2);
  }
  //#endregion

  //#region Reset Tests
  /**
   * Method testResetRestoresTokens
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testResetRestoresTokens(): void
  {
    // Consume some tokens
    $this->adapter->consume(key: $this->testKey);
    $this->adapter->consume(key: $this->testKey);
    $this->adapter->consume(key: $this->testKey);

    // Reset
    $this->adapter->reset(key: $this->testKey);

    // Should have full tokens again
    $result = $this->adapter->consume(key: $this->testKey);

    $this->assertTrue(condition: $result->accepted);
    // Should have 4 remaining (5 - 1 = 4 after first consume post-reset)
    $this->assertEquals(expected: 4, actual: $result->remainingTokens);
  }

  /**
   * Method testResetAfterLimitExceeded
   *
   * @access public
   *
   * @return void
   */
  #[Test]
  public function testResetAfterLimitExceeded(): void
  {
    // Exhaust limit
    for ($i = 0; $i < 6; $i++) {
      $this->adapter->consume(key: $this->testKey);
    }

    // Verify rejected
    $rejectedResult = $this->adapter->consume(key: $this->testKey);
    $this->assertFalse(condition: $rejectedResult->accepted);

    // Reset
    $this->adapter->reset(key: $this->testKey);

    // Should be accepted again
    $result = $this->adapter->consume(key: $this->testKey);
    $this->assertTrue(condition: $result->accepted);
  }
  //#endregion
}
