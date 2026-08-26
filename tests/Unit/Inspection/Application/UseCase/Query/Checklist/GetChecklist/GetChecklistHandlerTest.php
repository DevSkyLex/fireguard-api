<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Checklist\GetChecklist;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\{
  ChecklistItemResult,
  GetChecklistHandler,
  GetChecklistQuery,
  GetChecklistResult
};
use Inspection\Domain\Exception\ChecklistNotFoundException;
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test GetChecklistHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetChecklistHandler::class)]
final class GetChecklistHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440030';

  #[Test]
  public function itReturnsTheChecklistWithItsItems(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeChecklist());

    $handler = new GetChecklistHandler(checklistRepository: $repository);

    $result = $handler->__invoke(new GetChecklistQuery(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
    ));

    self::assertInstanceOf(GetChecklistResult::class, $result);
    self::assertSame(self::CL_ID, $result->checklistId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame('Fire Safety Checklist', $result->name);
    self::assertSame('v1.0', $result->version);
    self::assertSame('active', $result->status);
    self::assertSame('CHK-1', $result->referenceCode);
    self::assertCount(1, $result->items);
    self::assertInstanceOf(ChecklistItemResult::class, $result->items[0]);
    self::assertSame('Check pressure', $result->items[0]->label);
  }

  #[Test]
  public function itThrowsWhenChecklistNotFound(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new GetChecklistHandler(checklistRepository: $repository);

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new GetChecklistQuery(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenOrganizationMismatch(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeChecklist());

    $handler = new GetChecklistHandler(checklistRepository: $repository);

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new GetChecklistQuery(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      checklistId: self::CL_ID,
    ));
  }

  #[Test]
  public function itThrowsInvalidArgumentOnMalformedIdentifier(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);

    $handler = new GetChecklistHandler(checklistRepository: $repository);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new GetChecklistQuery(
      organizationId: self::ORG_ID,
      checklistId: 'not-a-uuid',
    ));
  }

  private function makeChecklist(): Checklist
  {
    return Checklist::create(
      id: ChecklistId::fromString(self::CL_ID),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
      items: [ChecklistItem::create(id: 'item-1', label: 'Check pressure', position: 0)],
      referenceCode: 'CHK-1',
    );
  }
}
