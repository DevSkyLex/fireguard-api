<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\ApprovalRequestNotPendingException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ApprovalRequestNotPendingException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequestNotPendingException::class)]
final class ApprovalRequestNotPendingExceptionTest extends TestCase
{
  #[Test]
  public function testWithIdBuildsMessage(): void
  {
    $exception = ApprovalRequestNotPendingException::withId('req-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Approval request with ID "req-1" is no longer pending.', $exception->getMessage());
  }
}
