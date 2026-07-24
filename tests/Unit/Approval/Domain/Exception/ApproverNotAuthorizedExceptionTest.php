<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Exception;

use Approval\Domain\Exception\ApproverNotAuthorizedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test ApproverNotAuthorizedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApproverNotAuthorizedException::class)]
final class ApproverNotAuthorizedExceptionTest extends TestCase
{
  #[Test]
  public function testBelowMinimumRoleBuildsMessage(): void
  {
    $exception = ApproverNotAuthorizedException::belowMinimumRole('admin');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Deciding this request requires at least the "admin" role.', $exception->getMessage());
  }
}
