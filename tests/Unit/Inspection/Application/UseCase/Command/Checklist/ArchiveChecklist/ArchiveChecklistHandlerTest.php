<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist;

use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Application\UseCase\Command\Checklist\ArchiveChecklist\{
  ArchiveChecklistCommand,
  ArchiveChecklistHandler,
  ArchiveChecklistResult
};
use Inspection\Domain\Exception\{ChecklistArchivedException, ChecklistNotFoundException};
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test ArchiveChecklistHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ArchiveChecklistHandler::class)]
final class ArchiveChecklistHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440030';

  #[Test]
  public function itArchivesTheChecklistAndSaves(): void
  {
    $checklist = $this->makeChecklist();

    /** @var ChecklistRepositoryPort&MockObject $repository */
    $repository = $this->createMock(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($checklist);
    $repository->expects(self::once())->method('save')->with($checklist);

    $handler = new ArchiveChecklistHandler(checklistRepository: $repository);

    $result = $handler->__invoke(new ArchiveChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
    ));

    self::assertInstanceOf(ArchiveChecklistResult::class, $result);
    self::assertSame(self::CL_ID, $result->checklistId);
    self::assertSame('archived', $result->status);
    self::assertTrue($checklist->status()->isArchived());
  }

  #[Test]
  public function itThrowsWhenChecklistNotFound(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn(null);

    $handler = new ArchiveChecklistHandler(checklistRepository: $repository);

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new ArchiveChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
    ));
  }

  #[Test]
  public function itThrowsWhenOrganizationMismatch(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($this->makeChecklist());

    $handler = new ArchiveChecklistHandler(checklistRepository: $repository);

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new ArchiveChecklistCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      checklistId: self::CL_ID,
    ));
  }

  #[Test]
  public function itThrowsInvalidArgumentOnMalformedIdentifier(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);

    $handler = new ArchiveChecklistHandler(checklistRepository: $repository);

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new ArchiveChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: 'not-a-uuid',
    ));
  }

  #[Test]
  public function itPropagatesArchivedExceptionWhenAlreadyArchived(): void
  {
    $checklist = $this->makeChecklist();
    $checklist->archive();

    $repository = $this->createStub(ChecklistRepositoryPort::class);
    $repository->method('findById')->willReturn($checklist);

    $handler = new ArchiveChecklistHandler(checklistRepository: $repository);

    $this->expectException(ChecklistArchivedException::class);

    $handler->__invoke(new ArchiveChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
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
    );
  }
}
