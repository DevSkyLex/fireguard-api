<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\NonConformityStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test NonConformityStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformityStatus::class)]
final class NonConformityStatusTest extends TestCase
{
  #[Test]
  public function itExposesAllValues(): void
  {
    self::assertSame(['open', 'in_progress', 'done', 'waived'], NonConformityStatus::values());
  }

  #[Test]
  public function itTreatsDoneAndWaivedAsResolved(): void
  {
    self::assertTrue(NonConformityStatus::DONE->isResolved());
    self::assertTrue(NonConformityStatus::WAIVED->isResolved());
  }

  #[Test]
  public function itTreatsOpenAndInProgressAsUnresolved(): void
  {
    self::assertFalse(NonConformityStatus::OPEN->isResolved());
    self::assertFalse(NonConformityStatus::IN_PROGRESS->isResolved());
  }

  #[Test]
  public function itLabelsEveryCase(): void
  {
    self::assertSame('Open', NonConformityStatus::OPEN->label());
    self::assertSame('In progress', NonConformityStatus::IN_PROGRESS->label());
    self::assertSame('Done', NonConformityStatus::DONE->label());
    self::assertSame('Waived', NonConformityStatus::WAIVED->label());
  }
}
