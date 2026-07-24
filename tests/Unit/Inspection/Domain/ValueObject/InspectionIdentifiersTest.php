<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\ValueObject;

use Inspection\Domain\ValueObject\{
  ChecklistId,
  ChecklistOrganizationId,
  InspectionAttachmentId,
  InspectionChecklistId,
  InspectionEquipmentId,
  InspectionFacilityId,
  InspectionId,
  InspectionOrganizationId,
  NonConformityId,
  NonConformityInspectionId
};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test Inspection module UUID identifiers.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistId::class)]
#[CoversClass(ChecklistOrganizationId::class)]
#[CoversClass(InspectionAttachmentId::class)]
#[CoversClass(InspectionChecklistId::class)]
#[CoversClass(InspectionEquipmentId::class)]
#[CoversClass(InspectionFacilityId::class)]
#[CoversClass(InspectionId::class)]
#[CoversClass(InspectionOrganizationId::class)]
#[CoversClass(NonConformityId::class)]
#[CoversClass(NonConformityInspectionId::class)]
final class InspectionIdentifiersTest extends TestCase
{
  private const string UUID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  #[Test]
  public function itBuildsEveryIdentifierFromAValidUuid(): void
  {
    self::assertSame(self::UUID, (string) ChecklistId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) ChecklistOrganizationId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionAttachmentId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionChecklistId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionEquipmentId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionFacilityId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) InspectionOrganizationId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) NonConformityId::fromString(self::UUID));
    self::assertSame(self::UUID, (string) NonConformityInspectionId::fromString(self::UUID));
  }

  #[Test]
  public function itComparesIdentifiersByValue(): void
  {
    self::assertTrue(
      InspectionId::fromString(self::UUID)->equals(InspectionId::fromString(self::UUID)),
    );
  }

  #[Test]
  public function itRejectsAnInvalidUuid(): void
  {
    $this->expectException(InvalidValueException::class);

    InspectionId::fromString('not-a-uuid');
  }
}
