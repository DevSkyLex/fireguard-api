<?php

declare(strict_types=1);

namespace Tests\Unit\Assistant\Domain\ValueObject;

use Assistant\Domain\ValueObject\AssistantMessageStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AssistantMessageStatusTest.
 *
 * @category ValueObject Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AssistantMessageStatus::class)]
final class AssistantMessageStatusTest extends TestCase
{
  #[Test]
  public function testValuesReturnsEveryCase(): void
  {
    self::assertSame(['pending', 'streaming', 'complete', 'failed'], AssistantMessageStatus::values());
  }

  #[Test]
  public function testPendingCanTransitionToStreamingAndFailedOnly(): void
  {
    self::assertTrue(AssistantMessageStatus::PENDING->canTransitionTo(AssistantMessageStatus::STREAMING));
    self::assertTrue(AssistantMessageStatus::PENDING->canTransitionTo(AssistantMessageStatus::FAILED));
    self::assertFalse(AssistantMessageStatus::PENDING->canTransitionTo(AssistantMessageStatus::COMPLETE));
    self::assertFalse(AssistantMessageStatus::PENDING->canTransitionTo(AssistantMessageStatus::PENDING));
  }

  #[Test]
  public function testStreamingCanTransitionToCompleteAndFailedOnly(): void
  {
    self::assertTrue(AssistantMessageStatus::STREAMING->canTransitionTo(AssistantMessageStatus::COMPLETE));
    self::assertTrue(AssistantMessageStatus::STREAMING->canTransitionTo(AssistantMessageStatus::FAILED));
    self::assertFalse(AssistantMessageStatus::STREAMING->canTransitionTo(AssistantMessageStatus::PENDING));
    self::assertFalse(AssistantMessageStatus::STREAMING->canTransitionTo(AssistantMessageStatus::STREAMING));
  }

  #[Test]
  public function testCompleteAndFailedAreTerminal(): void
  {
    self::assertTrue(AssistantMessageStatus::COMPLETE->isTerminal());
    self::assertTrue(AssistantMessageStatus::FAILED->isTerminal());
    self::assertFalse(AssistantMessageStatus::PENDING->isTerminal());
    self::assertFalse(AssistantMessageStatus::STREAMING->isTerminal());

    foreach (AssistantMessageStatus::cases() as $target) {
      self::assertFalse(AssistantMessageStatus::COMPLETE->canTransitionTo($target));
      self::assertFalse(AssistantMessageStatus::FAILED->canTransitionTo($target));
    }
  }
}
