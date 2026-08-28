<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Domain\ValueObject;

use Import\Domain\ValueObject\ImportKind;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ImportKind.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ImportKind::class)]
final class ImportKindTest extends TestCase
{
  #[Test]
  public function itExposesTheExpectedBackingValues(): void
  {
    self::assertSame('equipment', ImportKind::EQUIPMENT->value);
    self::assertSame('facility', ImportKind::FACILITY->value);
    self::assertSame('member', ImportKind::MEMBER->value);
  }

  #[Test]
  public function itReturnsAllSupportedValues(): void
  {
    self::assertSame(['equipment', 'facility', 'member'], ImportKind::values());
  }

  #[Test]
  public function itResolvesFromABackingValue(): void
  {
    self::assertSame(ImportKind::FACILITY, ImportKind::from('facility'));
  }
}
