<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Domain\ValueObject;

use Facility\Domain\ValueObject\AttachmentKind;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AttachmentKindTest.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AttachmentKind::class)]
final class AttachmentKindTest extends TestCase
{
  #[Test]
  public function testValuesListsBothCases(): void
  {
    self::assertSame(['document', 'floor_plan'], AttachmentKind::values());
  }

  #[Test]
  public function testDocumentCarriesNoMimeRestriction(): void
  {
    self::assertNull(AttachmentKind::DOCUMENT->allowedMimeTypes());
  }

  #[Test]
  public function testFloorPlanRestrictsToTheFourImageTypes(): void
  {
    self::assertSame(
      ['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'],
      AttachmentKind::FLOOR_PLAN->allowedMimeTypes(),
    );
  }

  #[Test]
  public function testFromStringResolvesBothValues(): void
  {
    self::assertSame(AttachmentKind::DOCUMENT, AttachmentKind::from('document'));
    self::assertSame(AttachmentKind::FLOOR_PLAN, AttachmentKind::from('floor_plan'));
  }

  #[Test]
  public function testTryFromReturnsNullForAnUnknownValue(): void
  {
    /** @phpstan-ignore staticMethod.alreadyNarrowedType */
    self::assertNull(AttachmentKind::tryFrom('not-a-kind'));
  }
}
