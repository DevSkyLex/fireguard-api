<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Checklist;

use DateTimeImmutable;
use Inspection\Domain\Exception\ChecklistArchivedException;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, ChecklistStatus};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

use function str_repeat;

/**
 * Test ChecklistTest.
 *
 * @category Domain Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(Checklist::class)]
final class ChecklistTest extends TestCase
{
  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440020';

  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440021';
  // #endregion

  // #region Methods
  #[Test]
  public function testCreateReturnsActiveStatus(): void
  {
    $checklist = $this->makeChecklist();

    self::assertSame(ChecklistStatus::ACTIVE, $checklist->status());
  }

  #[Test]
  public function testCreateStoresAllProperties(): void
  {
    $item = ChecklistItem::create(id: 'item-1', label: 'Check pressure', position: 0);

    $checklist = Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: '  Extinguisher Checklist  ',
      version: '  v2.0  ',
      items: [$item],
    );

    self::assertSame(self::CL_ID, (string) $checklist->id());
    self::assertSame(self::ORG_ID, (string) $checklist->organizationId());
    self::assertSame('Extinguisher Checklist', $checklist->name());
    self::assertSame('v2.0', $checklist->version());
    self::assertCount(1, $checklist->items());
  }

  #[Test]
  public function testCreateThrowsOnEmptyName(): void
  {
    $this->expectException(InvalidArgumentException::class);

    Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: '   ',
      version: 'v1.0',
    );
  }

  #[Test]
  public function testCreateThrowsWhenNameTooLong(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('name must be at most 255 characters');

    Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: str_repeat('x', 256),
      version: 'v1.0',
    );
  }

  #[Test]
  public function testCreateThrowsOnEmptyVersion(): void
  {
    $this->expectException(InvalidArgumentException::class);

    Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Valid Name',
      version: '   ',
    );
  }

  #[Test]
  public function testArchiveTransitionsToArchivedStatus(): void
  {
    $checklist = $this->makeChecklist();

    $checklist->archive();

    self::assertSame(ChecklistStatus::ARCHIVED, $checklist->status());
  }

  #[Test]
  public function testArchiveThrowsWhenAlreadyArchived(): void
  {
    $checklist = $this->makeChecklist();
    $checklist->archive();

    $this->expectException(ChecklistArchivedException::class);

    $checklist->archive();
  }

  #[Test]
  public function testCreateNormalizesReferenceCode(): void
  {
    $checklist = Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
      referenceCode: '  CHK-EXT-Q  ',
    );

    self::assertSame('CHK-EXT-Q', $checklist->referenceCode());
  }

  #[Test]
  public function testCreateTreatsBlankReferenceCodeAsNull(): void
  {
    $checklist = Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
      referenceCode: '   ',
    );

    self::assertNull($checklist->referenceCode());
  }

  #[Test]
  public function testCreateThrowsWhenReferenceCodeTooLong(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('reference code must be at most 40 characters');

    Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
      referenceCode: str_repeat('x', 41),
    );
  }

  #[Test]
  public function testUpdateChangesNameAndReferenceCode(): void
  {
    $checklist = $this->makeChecklist();

    $checklist->update(
      name: 'Renamed Checklist',
      hasName: true,
      referenceCode: 'CHK-EXT-Q',
      hasReferenceCode: true,
    );

    self::assertSame('Renamed Checklist', $checklist->name());
    self::assertSame('CHK-EXT-Q', $checklist->referenceCode());
  }

  #[Test]
  public function testUpdateClearsReferenceCodeWhenProvidedAsNull(): void
  {
    $checklist = Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
      referenceCode: 'CHK-EXT-Q',
    );

    $checklist->update(referenceCode: null, hasReferenceCode: true);

    self::assertNull($checklist->referenceCode());
  }

  #[Test]
  public function testUpdateLeavesFieldsUnchangedWhenNotProvided(): void
  {
    $checklist = $this->makeChecklist();

    $checklist->update();

    self::assertSame('Fire Safety Checklist', $checklist->name());
    self::assertNull($checklist->referenceCode());
    self::assertCount(0, $checklist->items());
  }

  #[Test]
  public function testUpdateReplacesItemList(): void
  {
    $checklist = $this->makeChecklist();
    $newItem = ChecklistItem::create(id: 'item-2', label: 'Check hose', position: 0);

    $checklist->update(items: [$newItem], hasItems: true);

    self::assertCount(1, $checklist->items());
    self::assertSame('item-2', $checklist->items()[0]->id());
  }

  #[Test]
  public function testUpdateThrowsWhenChecklistArchived(): void
  {
    $checklist = $this->makeChecklist();
    $checklist->archive();

    $this->expectException(ChecklistArchivedException::class);

    $checklist->update(name: 'Renamed', hasName: true);
  }

  #[Test]
  public function testCreateThrowsWhenVersionTooLong(): void
  {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('version must be at most 50 characters');

    Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: str_repeat('v', 51),
    );
  }

  #[Test]
  public function testCreateInitializesEqualTimestamps(): void
  {
    $checklist = $this->makeChecklist();

    self::assertSame($checklist->createdAt(), $checklist->updatedAt());
  }

  #[Test]
  public function testReconstituteRestoresPersistedState(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01 10:00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02 12:30:00');
    $item = ChecklistItem::reconstitute(
      id: 'item-9',
      label: 'Verify seal',
      position: 3,
      required: false,
      description: 'Detailed note',
    );

    $checklist = Checklist::reconstitute(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Persisted Checklist',
      version: 'v3.0',
      status: ChecklistStatus::ARCHIVED,
      items: [$item],
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      referenceCode: 'CHK-REF',
    );

    self::assertSame(self::CL_ID, (string) $checklist->id());
    self::assertSame(self::ORG_ID, (string) $checklist->organizationId());
    self::assertSame('Persisted Checklist', $checklist->name());
    self::assertSame('v3.0', $checklist->version());
    self::assertSame(ChecklistStatus::ARCHIVED, $checklist->status());
    self::assertSame('CHK-REF', $checklist->referenceCode());
    self::assertSame($createdAt, $checklist->createdAt());
    self::assertSame($updatedAt, $checklist->updatedAt());
    self::assertCount(1, $checklist->items());
    self::assertSame('item-9', $checklist->items()[0]->id());
  }

  #[Test]
  public function testUpdateSkipsNameWhenProvidedAsNull(): void
  {
    $checklist = $this->makeChecklist();

    $checklist->update(name: null, hasName: true);

    self::assertSame('Fire Safety Checklist', $checklist->name());
  }

  // #region Helpers
  private function makeChecklist(): Checklist
  {
    return Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
    );
  }
  // #endregion
}
