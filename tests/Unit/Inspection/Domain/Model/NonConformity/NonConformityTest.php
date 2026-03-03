<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\NonConformity;

use DateTimeImmutable;
use Inspection\Domain\Exception\NonConformityAlreadyResolvedException;
use Inspection\Domain\Model\NonConformity\NonConformity;
use Inspection\Domain\ValueObject\{
  NonConformityId,
  NonConformityInspectionId,
  NonConformitySeverity,
  NonConformityStatus
};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test NonConformityTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(NonConformity::class)]
final class NonConformityTest extends TestCase
{
  private const string NC_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string INSP_ID = '550e8400-e29b-41d4-a716-446655440011';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateReturnsOpenStatus(): void
  {
    $nc = $this->makeNonConformity();

    self::assertSame(NonConformityStatus::OPEN, $nc->status());
  }

  #[Test]
  public function testCreateStoresAllProperties(): void
  {
    $dueAt = new DateTimeImmutable('2026-03-01T00:00:00+00:00');

    $nc = NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Valve damaged',
      severity: NonConformitySeverity::CRITICAL,
      dueAt: $dueAt,
      notes: '  Fix urgently  ',
    );

    self::assertSame(self::NC_ID, (string) $nc->id());
    self::assertSame(self::INSP_ID, (string) $nc->inspectionId());
    self::assertSame('Valve damaged', $nc->description());
    self::assertSame(NonConformitySeverity::CRITICAL, $nc->severity());
    self::assertSame($dueAt, $nc->dueAt());
    self::assertSame('Fix urgently', $nc->notes());
    self::assertNull($nc->resolvedAt());
  }

  #[Test]
  public function testCreateThrowsOnEmptyDescription(): void
  {
    $this->expectException(InvalidArgumentException::class);

    NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: '   ',
      severity: NonConformitySeverity::LOW,
    );
  }

  #[Test]
  public function testCreateThrowsWhenDescriptionTooLong(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('description must be at most 2000 characters');

    NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: str_repeat('x', 2001),
      severity: NonConformitySeverity::LOW,
    );
  }

  #[Test]
  public function testUpdateStatusTransitionsToInProgress(): void
  {
    $nc = $this->makeNonConformity();

    $nc->updateStatus(NonConformityStatus::IN_PROGRESS);

    self::assertSame(NonConformityStatus::IN_PROGRESS, $nc->status());
    self::assertNull($nc->resolvedAt());
  }

  #[Test]
  public function testUpdateStatusSetsResolvedAtWhenDone(): void
  {
    $nc = $this->makeNonConformity();

    $nc->updateStatus(NonConformityStatus::DONE);

    self::assertSame(NonConformityStatus::DONE, $nc->status());
    self::assertInstanceOf(DateTimeImmutable::class, $nc->resolvedAt());
  }

  #[Test]
  public function testUpdateStatusSetsResolvedAtWhenWaived(): void
  {
    $nc = $this->makeNonConformity();

    $nc->updateStatus(NonConformityStatus::WAIVED);

    self::assertSame(NonConformityStatus::WAIVED, $nc->status());
    self::assertInstanceOf(DateTimeImmutable::class, $nc->resolvedAt());
  }

  #[Test]
  public function testUpdateStatusThrowsWhenAlreadyResolved(): void
  {
    $nc = $this->makeNonConformity();
    $nc->updateStatus(NonConformityStatus::DONE);

    $this->expectException(NonConformityAlreadyResolvedException::class);

    $nc->updateStatus(NonConformityStatus::IN_PROGRESS);
  }

  #[Test]
  public function testUpdateStatusThrowsWhenWaivedAndUpdatedAgain(): void
  {
    $nc = $this->makeNonConformity();
    $nc->updateStatus(NonConformityStatus::WAIVED);

    $this->expectException(NonConformityAlreadyResolvedException::class);

    $nc->updateStatus(NonConformityStatus::DONE);
  }

  // #region Helpers
  private function makeNonConformity(): NonConformity
  {
    return NonConformity::create(
      id: NonConformityId::fromString(self::NC_ID),
      inspectionId: NonConformityInspectionId::fromString(self::INSP_ID),
      description: 'Extinguisher pressure below threshold',
      severity: NonConformitySeverity::HIGH,
    );
  }
  // #endregion
}
