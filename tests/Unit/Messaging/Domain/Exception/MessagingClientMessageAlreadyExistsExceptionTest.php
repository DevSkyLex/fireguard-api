<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\Exception;

use Messaging\Domain\Exception\MessagingClientMessageAlreadyExistsException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Test MessagingClientMessageAlreadyExistsException.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingClientMessageAlreadyExistsException::class)]
final class MessagingClientMessageAlreadyExistsExceptionTest extends TestCase
{
  #[Test]
  public function forClientIdBuildsAMessageCarryingTheClientId(): void
  {
    $exception = MessagingClientMessageAlreadyExistsException::forClientId('client-42');

    self::assertInstanceOf(RuntimeException::class, $exception);
    self::assertSame('A message with the client identifier "client-42" already exists.', $exception->getMessage());
  }
}
