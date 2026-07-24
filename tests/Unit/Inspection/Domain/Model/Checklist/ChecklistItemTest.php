<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Checklist;

use Inspection\Domain\Model\Checklist\ChecklistItem;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test ChecklistItem.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistItem::class)]
final class ChecklistItemTest extends TestCase
{
  #[Test]
  public function itCreatesAnItemAndTrimsTheLabelAndDescription(): void
  {
    $item = ChecklistItem::create(
      id: 'item-1',
      label: '  Check pressure gauge  ',
      position: 3,
      required: false,
      description: '  Must read between 12 and 15 bar  ',
    );

    self::assertSame('item-1', $item->id());
    self::assertSame('Check pressure gauge', $item->label());
    self::assertSame(3, $item->position());
    self::assertFalse($item->required());
    self::assertSame('Must read between 12 and 15 bar', $item->description());
  }

  #[Test]
  public function itDefaultsRequiredToTrueAndDescriptionToNull(): void
  {
    $item = ChecklistItem::create(id: 'item-2', label: 'Verify seal', position: 0);

    self::assertTrue($item->required());
    self::assertNull($item->description());
  }

  #[Test]
  public function itNormalizesAnEmptyDescriptionToNull(): void
  {
    $item = ChecklistItem::create(id: 'item-3', label: 'Verify seal', position: 0, description: '   ');

    self::assertNull($item->description());
  }

  #[Test]
  public function itRejectsAnEmptyLabel(): void
  {
    $this->expectException(InvalidArgumentException::class);

    ChecklistItem::create(id: 'item-4', label: '   ', position: 0);
  }

  #[Test]
  public function itRejectsAnOverlongLabel(): void
  {
    $this->expectException(InvalidArgumentException::class);

    ChecklistItem::create(id: 'item-5', label: str_repeat('a', 256), position: 0);
  }

  #[Test]
  public function itRejectsAnOverlongDescription(): void
  {
    $this->expectException(InvalidArgumentException::class);

    ChecklistItem::create(id: 'item-6', label: 'Verify seal', position: 0, description: str_repeat('d', 1001));
  }

  #[Test]
  public function itRejectsANegativePosition(): void
  {
    $this->expectException(InvalidArgumentException::class);

    ChecklistItem::create(id: 'item-7', label: 'Verify seal', position: -1);
  }

  #[Test]
  public function itReconstitutesWithoutNormalization(): void
  {
    $item = ChecklistItem::reconstitute(
      id: 'item-8',
      label: 'Raw label',
      position: 5,
      required: true,
      description: 'Raw description',
    );

    self::assertSame('item-8', $item->id());
    self::assertSame('Raw label', $item->label());
    self::assertSame(5, $item->position());
    self::assertTrue($item->required());
    self::assertSame('Raw description', $item->description());
  }
}
