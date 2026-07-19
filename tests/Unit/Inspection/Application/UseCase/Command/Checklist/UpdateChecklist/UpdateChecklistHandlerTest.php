<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Checklist\UpdateChecklist;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Inspection\Application\Port\Outbound\{ChecklistRepositoryPort, InspectionRepositoryPort};
use Inspection\Application\UseCase\Command\Checklist\UpdateChecklist\{
  UpdateChecklistCommand,
  UpdateChecklistHandler,
  UpdateChecklistResult
};
use Inspection\Domain\Exception\{
  ChecklistArchivedException,
  ChecklistInUseException,
  ChecklistNotFoundException,
  ChecklistReferenceCodeAlreadyExistsException
};
use Inspection\Domain\Model\Checklist\{Checklist, ChecklistItem};
use Inspection\Domain\ValueObject\{ChecklistId, ChecklistOrganizationId};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

/**
 * Test UpdateChecklistHandlerTest.
 *
 * @category Application Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(UpdateChecklistHandler::class)]
final class UpdateChecklistHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string CL_ID = '550e8400-e29b-41d4-a716-446655440030';

  #[Test]
  public function testInvokeUpdatesNameAndReferenceCodeAndSaves(): void
  {
    $checklist = $this->makeChecklist();

    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);
    $checklistRepository->expects(self::once())->method('save')->with($checklist);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('countByOrganizationId')->willReturn(0);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $inspectionRepository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $result = $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      name: 'Renamed Checklist',
      referenceCode: 'CHK-EXT-Q',
      hasName: true,
      hasReferenceCode: true,
    ));

    self::assertInstanceOf(UpdateChecklistResult::class, $result);
    self::assertSame(self::CL_ID, $result->checklistId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertInstanceOf(DateTimeImmutable::class, $result->updatedAt);
    self::assertSame('Renamed Checklist', $checklist->name());
    self::assertSame('CHK-EXT-Q', $checklist->referenceCode());
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistNotFound(): void
  {
    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn(null);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      name: 'Renamed',
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationMismatch(): void
  {
    $checklist = $this->makeChecklist();

    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistNotFoundException::class);

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: '550e8400-e29b-41d4-a716-999999999999',
      checklistId: self::CL_ID,
      name: 'Renamed',
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenChecklistArchived(): void
  {
    $checklist = $this->makeChecklist();
    $checklist->archive();

    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistArchivedException::class);

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      name: 'Renamed',
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenNameNullButProvided(): void
  {
    $checklist = $this->makeChecklist();

    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $this->createStub(InspectionRepositoryPort::class),
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Field "name" cannot be null when provided.');

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      name: null,
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsChecklistInUseExceptionWhenItemsChangedAndReferencedByInspections(): void
  {
    $checklist = $this->makeChecklist();

    $checklistRepository = $this->createStub(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);

    /** @var InspectionRepositoryPort&MockObject $inspectionRepository */
    $inspectionRepository = $this->createMock(InspectionRepositoryPort::class);
    $inspectionRepository->expects(self::once())
      ->method('countByOrganizationId')
      ->willReturn(2);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $inspectionRepository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistInUseException::class);

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      items: [['label' => 'New item']],
      hasItems: true,
    ));
  }

  #[Test]
  public function testInvokeReplacesItemsWhenNotReferencedByInspections(): void
  {
    $checklist = $this->makeChecklist();

    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);
    $checklistRepository->expects(self::once())->method('save');

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);
    $inspectionRepository->method('countByOrganizationId')->willReturn(0);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $inspectionRepository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      items: [
        ['label' => 'Check pressure gauge', 'required' => true, 'position' => 0],
        ['label' => 'Check hose integrity', 'required' => false, 'position' => 1],
      ],
      hasItems: true,
    ));

    self::assertCount(2, $checklist->items());
    self::assertSame('Check pressure gauge', $checklist->items()[0]->label());
  }

  #[Test]
  public function testInvokeThrowsReferenceCodeAlreadyExistsOnUniqueConstraintViolation(): void
  {
    $checklist = $this->makeChecklist();

    $driverException = new class ('SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "uniq_checklist_organization_reference_code"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };
    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    /** @var ChecklistRepositoryPort&MockObject $checklistRepository */
    $checklistRepository = $this->createMock(ChecklistRepositoryPort::class);
    $checklistRepository->method('findById')->willReturn($checklist);
    $checklistRepository->expects(self::once())->method('save')->willThrowException($uniqueViolation);

    $inspectionRepository = $this->createStub(InspectionRepositoryPort::class);

    $handler = new UpdateChecklistHandler(
      checklistRepository: $checklistRepository,
      inspectionRepository: $inspectionRepository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistReferenceCodeAlreadyExistsException::class);

    $handler->__invoke(new UpdateChecklistCommand(
      organizationId: self::ORG_ID,
      checklistId: self::CL_ID,
      referenceCode: 'CHK-DUP',
      hasReferenceCode: true,
    ));
  }

  // #region Helpers
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

  private function makeUuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn('550e8400-e29b-41d4-a716-446655449999');

    return new UuidFactory($generator);
  }
  // #endregion
}
