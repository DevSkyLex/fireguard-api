<?php

declare(strict_types=1);

namespace Tests\Integration\Auth\Infrastructure\RateLimiter;

use Auth\Infrastructure\Adapter\RateLimiter\LoginRateLimiterAdapter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use Shared\Domain\ValueObject\RateLimitResult;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\RateLimiter\RateLimiterFactory;

use function uniqid;

/**
 * Class LoginRateLimiterAdapterTest.
 *
 * Integration tests for the LoginRateLimiterAdapter.
 *
 * @category Integration Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 *
 * @covers \Auth\Infrastructure\Adapter\RateLimiter\LoginRateLimiterAdapter
 */
#[CoversClass(className: LoginRateLimiterAdapter::class)]
final class LoginRateLimiterAdapterTest extends KernelTestCase
{
  // #region Properties
  private LoginRateLimiterAdapter $adapter;

  private string $testKey;

  private RateLimiterFactory $loginLimiter;
  // #endregion

  // #region Setup
  protected function setUp(): void
  {
    self::bootKernel();
    $container = static::getContainer();

    /** @var RateLimiterFactory $loginLimiter */
    $loginLimiter = $container->get('limiter.login');
    $this->loginLimiter = $loginLimiter;
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
  // #endregion

  // #region Consume Tests
  /**
   * Method testConsumeReturnsAcceptedOnFirstAttempt.
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
   * Method testConsumeDecreasesRemainingTokens.
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
   * Method testConsumeReturnsRejectedWhenLimitExceeded.
   */
  #[Test]
  public function testConsumeReturnsRejectedWhenLimitExceeded(): void
  {
    $limit = $this->getLimit($this->testKey);

    // Consume all tokens for this key
    for ($i = 0; $i < $limit; ++$i) {
      $this->adapter->consume(key: $this->testKey);
    }

    // Next attempt should be rejected
    $result = $this->adapter->consume(key: $this->testKey);

    $this->assertFalse(condition: $result->accepted);
    $this->assertEquals(expected: 0, actual: $result->remainingTokens);
    $this->assertGreaterThanOrEqual(0, $result->retryAfter);
  }

  /**
   * Method testConsumeWithMultipleTokens.
   */
  #[Test]
  public function testConsumeWithMultipleTokens(): void
  {
    $limit = $this->getLimit($this->testKey);
    $result = $this->adapter->consume(key: $this->testKey, tokens: 3);

    $this->assertTrue(condition: $result->accepted);
    // Should have limit - 3 remaining
    $this->assertEquals(expected: $limit - 3, actual: $result->remainingTokens);
  }

  /**
   * Method testDifferentKeysAreIndependent.
   */
  #[Test]
  public function testDifferentKeysAreIndependent(): void
  {
    $key1 = $this->testKey . '_user1';
    $key2 = $this->testKey . '_user2';
    $limit = $this->getLimit($key1);

    // Exhaust limit for key1
    for ($i = 0; $i < $limit; ++$i) {
      $this->adapter->consume(key: $key1);
    }

    // key2 should still be accepted
    $result = $this->adapter->consume(key: $key2);

    $this->assertTrue(condition: $result->accepted);

    // Cleanup
    $this->adapter->reset(key: $key1);
    $this->adapter->reset(key: $key2);
  }
  // #endregion

  // #region Reset Tests
  /**
   * Method testResetRestoresTokens.
   */
  #[Test]
  public function testResetRestoresTokens(): void
  {
    $limit = $this->getLimit($this->testKey);

    // Consume some tokens
    $this->adapter->consume(key: $this->testKey);
    $this->adapter->consume(key: $this->testKey);
    $this->adapter->consume(key: $this->testKey);

    // Reset
    $this->adapter->reset(key: $this->testKey);

    // Should have full tokens again
    $result = $this->adapter->consume(key: $this->testKey);

    $this->assertTrue(condition: $result->accepted);
    // Should have limit - 1 remaining after first consume post-reset
    $this->assertEquals(expected: $limit - 1, actual: $result->remainingTokens);
  }

  /**
   * Method testResetAfterLimitExceeded.
   */
  #[Test]
  public function testResetAfterLimitExceeded(): void
  {
    $limit = $this->getLimit($this->testKey);

    // Exhaust limit
    for ($i = 0; $i < $limit + 1; ++$i) {
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
  // #endregion

  private function getLimit(string $key): int
  {
    $limiter = $this->loginLimiter->create(key: $key);
    $limit = $limiter->consume()->getLimit();
    $limiter->reset();

    return $limit;
  }
}
