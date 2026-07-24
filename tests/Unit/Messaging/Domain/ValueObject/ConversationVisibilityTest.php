<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\ConversationVisibility;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ConversationVisibility.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ConversationVisibility::class)]
final class ConversationVisibilityTest extends TestCase
{
  #[Test]
  public function itBacksEachCaseWithItsStringValue(): void
  {
    self::assertSame('subject', ConversationVisibility::SUBJECT->value);
    self::assertSame('participants', ConversationVisibility::PARTICIPANTS->value);
  }

  #[Test]
  public function fromResolvesTheBackingValue(): void
  {
    self::assertSame(ConversationVisibility::SUBJECT, ConversationVisibility::from('subject'));
    self::assertSame(ConversationVisibility::PARTICIPANTS, ConversationVisibility::from('participants'));
  }
}
