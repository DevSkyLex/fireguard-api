<?php

declare(strict_types=1);

namespace Tests\Unit\Import\Application\UseCase\Command\CreateImportJob;

use Import\Application\Port\Outbound\{ImportJobQueuePort, ImportJobRepositoryPort};
use Import\Application\UseCase\Command\CreateImportJob\{CreateImportJobCommand, CreateImportJobHandler};
use Import\Domain\Exception\{ImportAccessDeniedException, ImportJobNotFoundException};
use InvalidArgumentException;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{FileStoragePort, UuidGeneratorPort};

/**
 * Test CreateImportJobHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CreateImportJobHandler::class)]
final class CreateImportJobHandlerTest extends TestCase
{
  private const string GENERATED_ID = '018f0b68-6758-7a12-8a1d-3f0d97f65a01';

  private const string ORGANIZATION_ID = 'org-1';

  private const string USER_ID = 'user-1';

  #[Test]
  public function itPersistsWritesEnqueuesAndReturnsTheResultOnTheHappyPath(): void
  {
    $repository = $this->createMock(ImportJobRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('write')
      ->with(self::stringContains(self::GENERATED_ID), self::stringContains('fire_extinguisher'));

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::once())->method('dispatch')->with(self::GENERATED_ID);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.equipment.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
    );

    $result = $handler->__invoke($this->command('equipment'));

    self::assertSame(self::GENERATED_ID, $result->importJobId);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('equipment', $result->kind);
    self::assertSame('pending', $result->status);
    self::assertSame('equipment.csv', $result->originalFilename);
  }

  #[Test]
  public function itRequiresTheFacilitiesWritePermissionForAFacilityKind(): void
  {
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with(self::USER_ID, self::ORGANIZATION_ID, 'organization.facilities.write')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      queue: $this->createStub(ImportJobQueuePort::class),
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
    );

    $result = $handler->__invoke($this->command('facility'));

    self::assertSame('facility', $result->kind);
  }

  #[Test]
  public function itAnswersNotFoundWhenTheCallerIsNotAMemberOfTheTargetOrganization(): void
  {
    // Same 404 an unknown organization id produces, so the status cannot be
    // used to probe which organization identifiers are real.
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
    );

    $this->expectException(ImportJobNotFoundException::class);
    $this->expectExceptionMessage(self::ORGANIZATION_ID);

    $handler->__invoke($this->command('equipment'));
  }

  #[Test]
  public function itDeniesAMemberLackingTheKindWritePermission(): void
  {
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('write');

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $handler = new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
    );

    $this->expectException(ImportAccessDeniedException::class);
    $this->expectExceptionMessage('organization.equipment.write');

    $handler->__invoke($this->command('equipment'));
  }

  #[Test]
  public function itRejectsAnEmptyFileName(): void
  {
    $handler = $this->stubbedHandler();

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      fileName: '   ',
      contents: 'type',
      mimeType: 'text/csv',
      size: 4,
    ));
  }

  #[Test]
  public function itRejectsEmptyContents(): void
  {
    $handler = $this->stubbedHandler();

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: 'equipment',
      fileName: 'equipment.csv',
      contents: '',
      mimeType: 'text/csv',
      size: 0,
    ));
  }

  #[Test]
  public function itRejectsAnUnknownKind(): void
  {
    $handler = $this->stubbedHandler();

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke($this->command('unknown'));
  }

  #[Test]
  public function itDeletesTheStoredFileAndRethrowsWhenPersistenceFails(): void
  {
    $repository = $this->createStub(ImportJobRepositoryPort::class);
    $repository->method('save')->willThrowException(new RuntimeException('deadlock'));

    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())->method('write');
    $fileStorage->expects(self::once())
      ->method('delete')
      ->with(self::stringContains(self::GENERATED_ID));

    $queue = $this->createMock(ImportJobQueuePort::class);
    $queue->expects(self::never())->method('dispatch');

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new CreateImportJobHandler(
      repository: $repository,
      fileStorage: $fileStorage,
      queue: $queue,
      authorization: $authorization,
      uuidFactory: $this->uuidFactory(),
    );

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('deadlock');

    $handler->__invoke($this->command('equipment'));
  }

  private function command(string $kind): CreateImportJobCommand
  {
    return new CreateImportJobCommand(
      userId: self::USER_ID,
      organizationId: self::ORGANIZATION_ID,
      kind: $kind,
      fileName: $kind . '.csv',
      contents: "type\nfire_extinguisher\n",
      mimeType: 'text/csv',
      size: 25,
    );
  }

  private function uuidFactory(): UuidFactory
  {
    $generator = $this->createStub(UuidGeneratorPort::class);
    $generator->method('generate')->willReturn(self::GENERATED_ID);

    return new UuidFactory($generator);
  }

  private function stubbedHandler(): CreateImportJobHandler
  {
    return new CreateImportJobHandler(
      repository: $this->createStub(ImportJobRepositoryPort::class),
      fileStorage: $this->createStub(FileStoragePort::class),
      queue: $this->createStub(ImportJobQueuePort::class),
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
      uuidFactory: $this->uuidFactory(),
    );
  }
}
