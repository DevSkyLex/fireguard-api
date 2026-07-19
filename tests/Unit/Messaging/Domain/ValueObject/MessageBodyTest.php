<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\MessageBody;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function mb_strlen;
use function str_repeat;

/**
 * Test MessageBodyTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessageBody::class)]
final class MessageBodyTest extends TestCase
{
  #[Test]
  public function testFromStringTrimsTheBody(): void
  {
    $body = MessageBody::fromString("  Hello team  \n");

    self::assertSame('Hello team', $body->value);
  }

  #[Test]
  public function testFromStringRejectsABlankBody(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageBody::fromString('   ');
  }

  #[Test]
  public function testFromStringRejectsABodyLongerThanFourThousandCharacters(): void
  {
    $this->expectException(MessagingValidationException::class);

    MessageBody::fromString(str_repeat('a', 4001));
  }

  #[Test]
  public function testFromStringAcceptsABodyAtTheLengthBoundary(): void
  {
    $body = MessageBody::fromString(str_repeat('a', 4000));

    self::assertSame(4000, mb_strlen($body->value));
  }
}
