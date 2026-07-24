<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingNotFoundException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingNotFoundException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingNotFoundException::class)]
final class MessagingNotFoundExceptionTest extends TestCase
{
  #[Test]
  public function conversationBuildsAMessageCarryingTheId(): void
  {
    $exception = MessagingNotFoundException::conversation('conv-1');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('Conversation with ID "conv-1" not found.', $exception->getMessage());
  }

  #[Test]
  public function messageBuildsAMessageCarryingTheId(): void
  {
    $exception = MessagingNotFoundException::message('msg-1');

    self::assertSame('Message with ID "msg-1" not found.', $exception->getMessage());
  }
}
