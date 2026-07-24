<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingValidationException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingValidationException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingValidationException::class)]
final class MessagingValidationExceptionTest extends TestCase
{
  #[Test]
  public function itIsARuntimeExceptionCarryingItsMessage(): void
  {
    $exception = new MessagingValidationException('invalid input');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('invalid input', $exception->getMessage());
  }
}
