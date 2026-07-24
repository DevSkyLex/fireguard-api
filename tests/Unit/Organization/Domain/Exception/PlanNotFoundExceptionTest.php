<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Exception;

use Organization\Domain\Exception\PlanNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test PlanNotFoundExceptionTest.
 *
 * @category Exception Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PlanNotFoundException::class)]
final class PlanNotFoundExceptionTest extends TestCase
{
  // #region Methods
  #[Test]
  public function testWithIdCreatesMessage(): void
  {
    $exception = PlanNotFoundException::withId('plan-42');

    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Plan with ID "plan-42" not found.', $exception->getMessage());
  }

  #[Test]
  public function testWithKeyCreatesMessage(): void
  {
    $exception = PlanNotFoundException::withKey('starter');

    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('Plan with key "starter" not found.', $exception->getMessage());
  }

  #[Test]
  public function testDefaultCreatesMessage(): void
  {
    $exception = PlanNotFoundException::default();

    $this->assertInstanceOf(RuntimeException::class, $exception);
    $this->assertSame('No default plan is configured.', $exception->getMessage());
  }
  // #endregion
}
