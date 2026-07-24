<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Domain\ValueObject;

use Messaging\Domain\ValueObject\MessagingSubjectType;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingSubjectType.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingSubjectType::class)]
final class MessagingSubjectTypeTest extends TestCase
{
  #[Test]
  public function itBacksEachCaseWithItsStringValue(): void
  {
    self::assertSame('facility', MessagingSubjectType::FACILITY->value);
    self::assertSame('equipment', MessagingSubjectType::EQUIPMENT->value);
    self::assertSame('intervention', MessagingSubjectType::INTERVENTION->value);
    self::assertSame('non_conformity', MessagingSubjectType::NON_CONFORMITY->value);
    self::assertSame('channel', MessagingSubjectType::CHANNEL->value);
    self::assertSame('direct', MessagingSubjectType::DIRECT->value);
  }

  #[Test]
  public function valuesReturnsEveryBackingValue(): void
  {
    self::assertSame(
      ['facility', 'equipment', 'intervention', 'non_conformity', 'channel', 'direct'],
      MessagingSubjectType::values(),
    );
  }
}
