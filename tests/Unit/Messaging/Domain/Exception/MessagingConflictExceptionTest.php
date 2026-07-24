<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingConflictException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingConflictException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingConflictException::class)]
final class MessagingConflictExceptionTest extends TestCase
{
  #[Test]
  public function itIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new MessagingConflictException('hierarchy cycle');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('hierarchy cycle', $exception->getMessage());
  }
}
