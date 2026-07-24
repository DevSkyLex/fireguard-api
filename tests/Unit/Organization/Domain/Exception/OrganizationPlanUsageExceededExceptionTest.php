<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Domain\Exception;

use Organization\Domain\Exception\OrganizationPlanUsageExceededException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test OrganizationPlanUsageExceededException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(OrganizationPlanUsageExceededException::class)]
final class OrganizationPlanUsageExceededExceptionTest extends TestCase
{
  #[Test]
  public function testWithViolationsBuildsRuntimeException(): void
  {
    $exception = OrganizationPlanUsageExceededException::withViolations([
      'members' => ['usage' => 12, 'limit' => 5],
    ]);

    self::assertInstanceOf(OrganizationPlanUsageExceededException::class, $exception);
    self::assertInstanceOf(RuntimeException::class, $exception);
  }

  #[Test]
  public function testWithViolationsListsSingleResource(): void
  {
    $exception = OrganizationPlanUsageExceededException::withViolations([
      'members' => ['usage' => 12, 'limit' => 5],
    ]);

    self::assertStringContainsString('members 12/5', $exception->getMessage());
    self::assertStringContainsString('Confirm the downgrade to apply it anyway', $exception->getMessage());
  }

  #[Test]
  public function testWithViolationsJoinsMultipleResources(): void
  {
    $exception = OrganizationPlanUsageExceededException::withViolations([
      'members' => ['usage' => 12, 'limit' => 5],
      'teams' => ['usage' => 3, 'limit' => 1],
    ]);

    self::assertStringContainsString('members 12/5, teams 3/1', $exception->getMessage());
  }

  #[Test]
  public function testWithViolationsHandlesEmptyViolations(): void
  {
    $exception = OrganizationPlanUsageExceededException::withViolations([]);

    self::assertStringContainsString('Current usage exceeds the selected plan limits ()', $exception->getMessage());
  }
}
