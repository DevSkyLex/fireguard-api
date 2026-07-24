<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\FacilityStatus;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test FacilityStatus.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(FacilityStatus::class)]
final class FacilityStatusTest extends TestCase
{
  #[Test]
  public function testCaseValues(): void
  {
    self::assertSame('active', FacilityStatus::ACTIVE->value);
    self::assertSame('archived', FacilityStatus::ARCHIVED->value);
  }

  #[Test]
  public function testIsActiveReturnsTrueForActive(): void
  {
    self::assertTrue(FacilityStatus::ACTIVE->isActive());
  }

  #[Test]
  public function testIsActiveReturnsFalseForArchived(): void
  {
    self::assertFalse(FacilityStatus::ARCHIVED->isActive());
  }

  #[Test]
  public function testLabelForActive(): void
  {
    self::assertSame('Active', FacilityStatus::ACTIVE->label());
  }

  #[Test]
  public function testLabelForArchived(): void
  {
    self::assertSame('Archived', FacilityStatus::ARCHIVED->label());
  }

  #[Test]
  public function testFromReconstructsCase(): void
  {
    self::assertSame(FacilityStatus::ARCHIVED, FacilityStatus::from('archived'));
  }
}
