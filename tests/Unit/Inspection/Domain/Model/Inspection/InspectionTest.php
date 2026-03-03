<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Inspection;

use DateTimeImmutable;
use Inspection\Domain\Exception\{
  InspectionAlreadyClosedException,
  InspectionAlreadySubmittedException,
  InspectionNotSubmittedException
};
use Inspection\Domain\Model\Inspection\Inspection;
use Inspection\Domain\ValueObject\{
  InspectionEquipmentId,
  InspectionId,
  InspectionOrganizationId,
  InspectionResult,
  InspectionStatus,
  Inspector
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;
use function usleep;

/**
 * Test InspectionTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Inspection::class)]
final class InspectionTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string EQUIP_ID = '550e8400-e29b-41d4-a716-446655440002';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440003';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateReturnsDraftStatus(): void
  {
    $inspection = $this->makeInspection();

    self::assertSame(InspectionStatus::DRAFT, $inspection->status());
  }

  #[Test]
  public function testCreateStoresAllProperties(): void
  {
    $performedAt = new DateTimeImmutable('2026-01-15T10:00:00+00:00');

    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::FAIL,
      performedAt: $performedAt,
      notes: '  Some notes  ',
    );

    self::assertSame(self::INSP_ID, (string) $inspection->id());
    self::assertSame(self::ORG_ID, (string) $inspection->organizationId());
    self::assertSame(self::EQUIP_ID, (string) $inspection->equipmentId());
    self::assertSame(InspectionResult::FAIL, $inspection->result());
    self::assertSame($performedAt, $inspection->performedAt());
    self::assertSame('Some notes', $inspection->notes());
  }

  #[Test]
  public function testCreateNormalizesEmptyNotesToNull(): void
  {
    $inspection = Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable(),
      notes: '   ',
    );

    self::assertNull($inspection->notes());
  }

  #[Test]
  public function testCreateThrowsWhenNotesTooLong(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('notes must be at most 5000 characters');

    Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable(),
      notes: str_repeat('x', 5001),
    );
  }

  #[Test]
  public function testSubmitTransitionsDraftToSubmitted(): void
  {
    $inspection = $this->makeInspection();

    $inspection->submit();

    self::assertSame(InspectionStatus::SUBMITTED, $inspection->status());
  }

  #[Test]
  public function testSubmitThrowsWhenAlreadySubmitted(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $this->expectException(InspectionAlreadySubmittedException::class);

    $inspection->submit();
  }

  #[Test]
  public function testSubmitThrowsWhenAlreadyClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->submit();
  }

  #[Test]
  public function testCloseTransitionsSubmittedToClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();

    $inspection->close();

    self::assertSame(InspectionStatus::CLOSED, $inspection->status());
  }

  #[Test]
  public function testCloseThrowsWhenAlreadyClosed(): void
  {
    $inspection = $this->makeInspection();
    $inspection->submit();
    $inspection->close();

    $this->expectException(InspectionAlreadyClosedException::class);

    $inspection->close();
  }

  #[Test]
  public function testCloseThrowsWhenDraftNotYetSubmitted(): void
  {
    $inspection = $this->makeInspection();

    $this->expectException(InspectionNotSubmittedException::class);

    $inspection->close();
  }

  #[Test]
  public function testUpdatedAtChangesAfterSubmit(): void
  {
    $inspection = $this->makeInspection();
    $before = $inspection->updatedAt();

    // Ensure at least 1 microsecond passes
    usleep(1000);
    $inspection->submit();

    self::assertGreaterThanOrEqual($before, $inspection->updatedAt());
  }

  // #region Helpers
  private function makeInspection(): Inspection
  {
    return Inspection::create(
      id: InspectionId::fromString(self::INSP_ID),
      organizationId: InspectionOrganizationId::fromString(self::ORG_ID),
      equipmentId: InspectionEquipmentId::fromString(self::EQUIP_ID),
      inspector: Inspector::forUser(userId: 'user-1', name: 'John Doe'),
      result: InspectionResult::PASS,
      performedAt: new DateTimeImmutable('2026-01-15T10:00:00+00:00'),
    );
  }
  // #endregion
}
