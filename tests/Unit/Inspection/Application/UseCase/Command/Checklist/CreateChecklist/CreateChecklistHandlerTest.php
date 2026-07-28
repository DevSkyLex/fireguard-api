<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Application\UseCase\Command\Checklist\CreateChecklist;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Inspection\Application\Port\Outbound\ChecklistRepositoryPort;
use Inspection\Application\UseCase\Command\Checklist\CreateChecklist\{
  CreateChecklistCommand,
  CreateChecklistHandler,
  CreateChecklistResult
};
use Inspection\Domain\Exception\ChecklistReferenceCodeAlreadyExistsException;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\UuidGeneratorPort;

/**
 * Test CreateChecklistHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateChecklistHandler::class)]
final class CreateChecklistHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440001';

  private const string GENERATED_UUID = '550e8400-e29b-41d4-a716-446655449999';

  #[Test]
  public function itCreatesAChecklistWithItemsAndSaves(): void
  {
    /** @var ChecklistRepositoryPort&MockObject $repository */
    $repository = $this->createMock(ChecklistRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $handler = new CreateChecklistHandler(
      checklistRepository: $repository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $result = $handler->__invoke(new CreateChecklistCommand(
      organizationId: self::ORG_ID,
      name: 'Extinguisher Checklist',
      version: 'v1.0',
      items: [
        ['label' => 'Check pressure gauge', 'required' => true, 'position' => 0],
        ['label' => 'Check pin seal'],
      ],
      referenceCode: 'CHK-EXT',
    ));

    self::assertInstanceOf(CreateChecklistResult::class, $result);
    self::assertSame(self::GENERATED_UUID, $result->checklistId);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame('Extinguisher Checklist', $result->name);
    self::assertSame('v1.0', $result->version);
    self::assertSame('active', $result->status);
    self::assertSame('CHK-EXT', $result->referenceCode);
    self::assertCount(2, $result->items);
    self::assertSame('Check pressure gauge', $result->items[0]['label']);
    self::assertSame(1, $result->items[1]['position']);
  }

  #[Test]
  public function itThrowsInvalidArgumentOnMalformedOrganizationId(): void
  {
    $repository = $this->createStub(ChecklistRepositoryPort::class);

    $handler = new CreateChecklistHandler(
      checklistRepository: $repository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateChecklistCommand(
      organizationId: 'not-a-uuid',
      name: 'Checklist',
      version: 'v1.0',
    ));
  }

  #[Test]
  public function itMapsUniqueConstraintViolationToReferenceCodeConflict(): void
  {
    $driverException = new class ('duplicate key value violates unique constraint "uniq_checklist_organization_reference_code"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };
    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    /** @var ChecklistRepositoryPort&MockObject $repository */
    $repository = $this->createMock(ChecklistRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willThrowException($uniqueViolation);

    $handler = new CreateChecklistHandler(
      checklistRepository: $repository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(ChecklistReferenceCodeAlreadyExistsException::class);

    $handler->__invoke(new CreateChecklistCommand(
      organizationId: self::ORG_ID,
      name: 'Checklist',
      version: 'v1.0',
      referenceCode: 'CHK-DUP',
    ));
  }

  #[Test]
  public function itRethrowsPersistenceFailuresThatAreNotAReferenceCodeConflict(): void
  {
    /** @var ChecklistRepositoryPort&MockObject $repository */
    $repository = $this->createMock(ChecklistRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willThrowException(new RuntimeException('Connection lost.'));

    $handler = new CreateChecklistHandler(
      checklistRepository: $repository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Connection lost.');

    $handler->__invoke(new CreateChecklistCommand(
      organizationId: self::ORG_ID,
      name: 'Checklist',
      version: 'v1.0',
    ));
  }

  #[Test]
  public function itRethrowsAUniqueViolationRaisedByAnotherConstraint(): void
  {
    $driverException = new class ('duplicate key value violates unique constraint "uniq_checklist_something_else"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };
    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    /** @var ChecklistRepositoryPort&MockObject $repository */
    $repository = $this->createMock(ChecklistRepositoryPort::class);
    $repository->expects(self::once())->method('save')->willThrowException($uniqueViolation);

    $handler = new CreateChecklistHandler(
      checklistRepository: $repository,
      uuidFactory: $this->makeUuidFactory(),
    );

    $this->expectException(UniqueConstraintViolationException::class);

    $handler->__invoke(new CreateChecklistCommand(
      organizationId: self::ORG_ID,
      name: 'Checklist',
      version: 'v1.0',
      referenceCode: 'CHK-OTHER',
    ));
  }

  private function makeUuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::GENERATED_UUID);

    return new UuidFactory($generator);
  }
}
