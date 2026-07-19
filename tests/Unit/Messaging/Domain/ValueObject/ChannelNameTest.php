<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\Exception\MessagingValidationException;
use Messaging\Domain\ValueObject\ChannelName;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test ChannelNameTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChannelName::class)]
final class ChannelNameTest extends TestCase
{
  #[Test]
  public function testConstructorTrimsTheName(): void
  {
    $name = new ChannelName('  General  ');

    self::assertSame('General', (string) $name);
  }

  #[Test]
  public function testConstructorRejectsABlankName(): void
  {
    $this->expectException(MessagingValidationException::class);

    new ChannelName('   ');
  }

  #[Test]
  public function testConstructorRejectsANameShorterThanTwoCharacters(): void
  {
    $this->expectException(MessagingValidationException::class);

    new ChannelName('A');
  }

  #[Test]
  public function testConstructorRejectsANameLongerThanEightyCharacters(): void
  {
    $this->expectException(MessagingValidationException::class);

    new ChannelName(str_repeat('a', 81));
  }

  #[Test]
  public function testConstructorRejectsControlCharacters(): void
  {
    $this->expectException(MessagingValidationException::class);

    // A control character in the middle of the string, so it survives
    // trim()'s edge-only stripping (which — like PHP's default char list —
    // would otherwise remove a trailing "\x00" before the check runs).
    new ChannelName("Gen\x01eral");
  }

  #[Test]
  public function testEqualsComparesNormalizedValues(): void
  {
    $first = new ChannelName('General');
    $second = new ChannelName('  General  ');
    $third = new ChannelName('Announcements');

    self::assertTrue($first->equals($second));
    self::assertFalse($first->equals($third));
  }
}
