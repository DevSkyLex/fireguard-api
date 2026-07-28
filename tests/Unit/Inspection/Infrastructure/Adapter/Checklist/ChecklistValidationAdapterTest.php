<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Infrastructure\Adapter\Checklist;

use DateTimeImmutable;
use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId, ChecklistStatus};
use Inspection\Infrastructure\Adapter\Checklist\ChecklistValidationAdapter;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ChecklistValidationAdapterTest.
 *
 * @category Adapter Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ChecklistValidationAdapter::class)]
final class ChecklistValidationAdapterTest extends TestCase
{
  private const string CHECKLIST_ID = '550e8400-e29b-41d4-a716-446655440401';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440402';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440403';

  #[Test]
  public function testItAcceptsAnActiveChecklistOwnedByTheOrganization(): void
  {
    $adapter = new ChecklistValidationAdapter($this->repository($this->checklist(ChecklistStatus::ACTIVE)));

    $this->expectNotToPerformAssertions();

    $adapter->assertChecklistIsUsable(self::CHECKLIST_ID, self::ORGANIZATION_ID);
  }

  #[Test]
  public function testItRejectsAnUnknownChecklist(): void
  {
    $adapter = new ChecklistValidationAdapter($this->repository(null));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertChecklistIsUsable(self::CHECKLIST_ID, self::ORGANIZATION_ID);
  }

  #[Test]
  public function testItRejectsAChecklistOwnedByAnotherOrganization(): void
  {
    $adapter = new ChecklistValidationAdapter($this->repository($this->checklist(ChecklistStatus::ACTIVE)));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('not found');

    $adapter->assertChecklistIsUsable(self::CHECKLIST_ID, self::OTHER_ORGANIZATION_ID);
  }

  #[Test]
  public function testItRejectsAnArchivedChecklist(): void
  {
    $adapter = new ChecklistValidationAdapter($this->repository($this->checklist(ChecklistStatus::ARCHIVED)));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('archived');

    $adapter->assertChecklistIsUsable(self::CHECKLIST_ID, self::ORGANIZATION_ID);
  }

  private function repository(?Checklist $checklist): ChecklistRepositoryPort
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($checklist);

    return $repository;
  }

  private function checklist(ChecklistStatus $status): Checklist
  {
    $now = new DateTimeImmutable('2026-01-01T08:00:00+00:00');

    return Checklist::reconstitute(
      id: ChecklistId::fromString(self::CHECKLIST_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORGANIZATION_ID),
      name: 'Annual Safety Checklist',
      version: '1.0',
      status: $status,
      items: [],
      createdAt: $now,
      updatedAt: $now,
    );
  }
}
