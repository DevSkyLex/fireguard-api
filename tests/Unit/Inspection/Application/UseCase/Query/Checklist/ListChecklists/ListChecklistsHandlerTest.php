<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Application\UseCase\Query\Checklist\ListChecklists\{ListChecklistResult, ListChecklistsHandler, ListChecklistsQuery};
use Inspection\Domain\Model\Checklist\Checklist;
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Contract\Pagination\PaginatedResult;
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test ListChecklistsHandlerTest.
 *
 * L1.10b: pins that the list path carries a scalar `itemCount` resolved via
 * the repository's grouped count query, including the "checklist absent
 * from the count map" (zero-items) case, without ever materializing a full
 * `items` array for a list row.
 *
 * @category Application Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChecklistsHandler::class)]
final class ListChecklistsHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CHECKLIST_WITH_ITEMS_ID = '550e8400-e29b-41d4-a716-446655440020';

  private const string CHECKLIST_WITHOUT_ITEMS_ID = '550e8400-e29b-41d4-a716-446655440021';

  #[Test]
  public function testInvokeCarriesItemCountFromGroupedCountQueryIncludingZeroForMissingKey(): void
  {
    $checklistWithItems = $this->makeChecklist(self::CHECKLIST_WITH_ITEMS_ID);
    $checklistWithoutItems = $this->makeChecklist(self::CHECKLIST_WITHOUT_ITEMS_ID);

    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->method('findByOrganizationId')->willReturn([$checklistWithItems, $checklistWithoutItems]);
    $checklistRepository->method('countByOrganizationId')->willReturn(2);
    $checklistRepository->expects(self::once())
      ->method('countItemsGroupedByChecklistId')
      ->with(
        self::callback(static fn (ChecklistOrganizationId $organizationId): bool => self::ORG_ID === (string) $organizationId),
        [self::CHECKLIST_WITH_ITEMS_ID, self::CHECKLIST_WITHOUT_ITEMS_ID],
      )
      // Deliberately omits CHECKLIST_WITHOUT_ITEMS_ID: a checklist with zero
      // items is absent from the grouped-query result map, not present with
      // value 0 — the handler must default the missing key to 0.
      ->willReturn([self::CHECKLIST_WITH_ITEMS_ID => 3]);

    $handler = new ListChecklistsHandler($checklistRepository);

    $result = $handler->__invoke(new ListChecklistsQuery(organizationId: self::ORG_ID));

    self::assertInstanceOf(PaginatedResult::class, $result);
    self::assertCount(2, $result->items);
    self::assertSame(2, $result->total);

    [$first, $second] = $result->items;
    self::assertInstanceOf(ListChecklistResult::class, $first);
    self::assertSame(self::CHECKLIST_WITH_ITEMS_ID, $first->checklistId);
    self::assertSame(3, $first->itemCount);

    self::assertInstanceOf(ListChecklistResult::class, $second);
    self::assertSame(self::CHECKLIST_WITHOUT_ITEMS_ID, $second->checklistId);
    self::assertSame(0, $second->itemCount);
  }

  #[Test]
  public function testInvokeReturnsEmptyPageWithoutQueryingCountsWhenNoChecklists(): void
  {
    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->method('findByOrganizationId')->willReturn([]);
    $checklistRepository->method('countByOrganizationId')->willReturn(0);
    $checklistRepository->expects(self::once())
      ->method('countItemsGroupedByChecklistId')
      ->with(self::isInstanceOf(ChecklistOrganizationId::class), [])
      ->willReturn([]);

    $handler = new ListChecklistsHandler($checklistRepository);

    $result = $handler->__invoke(new ListChecklistsQuery(organizationId: self::ORG_ID));

    self::assertSame([], $result->items);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvokeRejectsAnUnknownStatusFilter(): void
  {
    $handler = new ListChecklistsHandler($this->createStub(ChecklistRepositoryPort::class));

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new ListChecklistsQuery(organizationId: self::ORG_ID, status: 'retired'));
  }

  private function makeChecklist(string $id): Checklist
  {
    return Checklist::create(
      id: ChecklistId::fromString($id),
      organizationId: ChecklistOrganizationId::fromString(self::ORG_ID),
      name: 'Fire Safety Checklist',
      version: 'v1.0',
    );
  }
}
