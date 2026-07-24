<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\SelfApprovalNotAllowedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test SelfApprovalNotAllowedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(SelfApprovalNotAllowedException::class)]
final class SelfApprovalNotAllowedExceptionTest extends TestCase
{
  #[Test]
  public function testCreateBuildsMessage(): void
  {
    $exception = SelfApprovalNotAllowedException::create();

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('The requester cannot decide on their own approval request.', $exception->getMessage());
  }
}
