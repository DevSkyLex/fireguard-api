<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingAttachmentNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingAttachmentNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAttachmentNotFoundException::class)]
final class MessagingAttachmentNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function withIdBuildsAMessageCarryingTheId(): void
  {
    $exception = MessagingAttachmentNotFoundException::withId('att-9');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Messaging attachment with ID "att-9" not found.', $exception->getMessage());
  }
}
