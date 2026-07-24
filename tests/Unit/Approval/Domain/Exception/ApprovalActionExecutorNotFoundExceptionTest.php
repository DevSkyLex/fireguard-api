<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\ApprovalActionExecutorNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ApprovalActionExecutorNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalActionExecutorNotFoundException::class)]
final class ApprovalActionExecutorNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testForActionTypeBuildsMessage(): void
  {
    $exception = ApprovalActionExecutorNotFoundException::forActionType('nc_waiver');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('No approval executor is registered for action type "nc_waiver".', $exception->getMessage());
  }
}
