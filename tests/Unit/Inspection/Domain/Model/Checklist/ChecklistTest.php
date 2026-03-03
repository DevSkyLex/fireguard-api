<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Domain\Model\Checklist;

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
