<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\ApprovalRequestNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ApprovalRequestNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestNotFoundException::class)]
final class ApprovalRequestNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsMessage(): void
  {
    $exception = ApprovalRequestNotFoundException::withId('req-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Approval request with ID "req-1" not found.', $exception->getMessage());
  }
}
