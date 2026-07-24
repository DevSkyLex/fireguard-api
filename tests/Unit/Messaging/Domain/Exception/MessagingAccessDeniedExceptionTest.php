<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingAccessDeniedException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAccessDeniedException::class)]
final class MessagingAccessDeniedExceptionTest extends TestCase
{
  #[Test]
  public function itIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new MessagingAccessDeniedException('forbidden');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('forbidden', $exception->getMessage());
  }
}
