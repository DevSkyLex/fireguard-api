<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\ValueObject;

use Assistant\Domain\ValueObject\AssistantMessageRole;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageRole.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageRole::class)]
final class AssistantMessageRoleTest extends TestCase
{
  #[Test]
  public function testBackingValues(): void
  {
    self::assertSame('user', AssistantMessageRole::USER->value);
    self::assertSame('assistant', AssistantMessageRole::ASSISTANT->value);
  }

  #[Test]
  public function testValuesReturnsEveryCase(): void
  {
    self::assertSame(['user', 'assistant'], AssistantMessageRole::values());
  }
}
