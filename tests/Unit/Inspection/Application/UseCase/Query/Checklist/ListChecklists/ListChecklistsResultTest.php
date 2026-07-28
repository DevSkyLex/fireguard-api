<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Query\Checklist\ListChecklists;

use DateTimeImmutable;
use Inspection\Application\UseCase\Query\Checklist\GetChecklist\GetChecklistResult;
use Inspection\Application\UseCase\Query\Checklist\ListChecklists\ListChecklistsResult;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListChecklistsResultTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListChecklistsResult::class)]
final class ListChecklistsResultTest extends TestCase
{
  #[Test]
  public function testItExposesTheChecklistList(): void
  {
    $now = new DateTimeImmutable('2026-02-01T08:00:00+00:00');
    $checklist = new GetChecklistResult(
      checklistId: 'checklist-1',
      organizationId: 'org-1',
      name: 'Annual Safety Checklist',
      version: '1.0',
      status: 'active',
      items: [],
      createdAt: $now,
      updatedAt: $now,
    );

    $result = new ListChecklistsResult([$checklist]);

    self::assertSame([$checklist], $result->checklists);
  }

  #[Test]
  public function testItAcceptsAnEmptyList(): void
  {
    self::assertSame([], new ListChecklistsResult([])->checklists);
  }
}
