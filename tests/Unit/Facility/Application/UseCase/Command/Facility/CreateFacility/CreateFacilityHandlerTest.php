<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Facility\Application\Port\Outbound\{FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort};
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler, CreateFacilityResult};
use Facility\Domain\Event\Facility\FacilityCreatedEvent;
use Facility\Domain\Exception\{FacilityArchivedException, FacilityCodeAlreadyExistsException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use Organization\Application\Contract\Quota\{OrganizationQuotaExceededException, OrganizationQuotaResource};
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;
use Throwable;

use function sprintf;

#[CoversClass(CreateFacilityHandler::class)]
final class CreateFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsWhenParentFacilityIdIsBlankString(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440980',
      type: 'site',
      name: 'HQ',
      parentFacilityId: '',
    ));
  }

  #[Test]
  public function testInvokeThrowsFacilityCodeAlreadyExistsOnUniqueConstraintViolation(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())
      ->method('findById');

    $driverException = new class ('SQLSTATE[23505]: duplicate key value violates unique constraint "uniq_facility_organization_code"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new UniqueConstraintViolationException($driverException, null));

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(FacilityCodeAlreadyExistsException::class);
    $this->expectExceptionMessage('Facility code "SITE-001" already exists for this organization.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440901',
      type: 'site',
      name: 'HQ',
      code: 'SITE-001',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentFacilityNotFound(): void
  {
    $parentId = '550e8400-e29b-41d4-a716-446655441910';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Facility with ID "%s" not found.', $parentId));

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655441911',
      type: 'building',
      name: 'Building A',
      parentFacilityId: $parentId,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentBelongsToAnotherOrganization(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441920';
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441921');
    $anotherOrgId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655441922');

    $parentFacility = Facility::create(
      id: $parentId,
      organizationId: $anotherOrgId,
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($parentFacility);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Parent facility must belong to the same organization.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'building',
      name: 'Building B',
      parentFacilityId: (string) $parentId,
    ));
  }

  #[Test]
  public function testInvokeReturnsResult(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-446655441930');
    $organizationId = '550e8400-e29b-41d4-a716-446655441931';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn($generatedId);

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'New HQ',
      code: 'HQ-01',
      address: '1 Main Street',
      metadata: ['region' => 'west'],
    ));

    self::assertInstanceOf(CreateFacilityResult::class, $result);
    self::assertSame((string) $generatedId, $result->facilityId);
    self::assertSame($organizationId, $result->organizationId);
    self::assertNull($result->parentFacilityId);
    self::assertSame('site', $result->type);
    self::assertSame('New HQ', $result->name);
    self::assertSame('HQ-01', $result->code);
    self::assertSame('active', $result->status);
    self::assertSame('1 Main Street', $result->address);
    self::assertSame(['region' => 'west'], $result->metadata);
  }

  #[Test]
  public function testInvokeReturnsResultWithLevelIndex(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-4466554419f1');
    $organizationId = '550e8400-e29b-41d4-a716-4466554419f2';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($generatedId);

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'floor',
      name: 'First Basement',
      levelIndex: -1,
    ));

    self::assertSame(-1, $result->levelIndex);
  }

  #[Test]
  public function testInvokeReturnsResultWithNullLevelIndexWhenOmitted(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-4466554419f3');
    $organizationId = '550e8400-e29b-41d4-a716-4466554419f4';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($generatedId);

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'HQ Without Level Index',
    ));

    self::assertNull($result->levelIndex);
  }

  #[Test]
  public function testInvokeThrowsWhenLevelIndexIsOutOfRange(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419f5'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility level index must be between -100 and 200.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419f6',
      type: 'floor',
      name: 'Too Deep',
      levelIndex: -101,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenMetadataFailsTheOrganizationSchema(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419a5'));

    $metadataRepository = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $metadataRepository->method('findByOrganizationId')->willReturn([
      \Facility\Domain\Model\MetadataField\FacilityMetadataField::reconstitute(
        id: \Facility\Domain\ValueObject\FacilityMetadataFieldId::fromString('550e8400-e29b-41d4-a716-4466554419a6'),
        organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-4466554419a4'),
        key: new \Facility\Domain\ValueObject\FacilityMetadataFieldKey('surface-m2'),
        label: new \Facility\Domain\ValueObject\FacilityMetadataFieldLabel('Surface (m²)'),
        fieldType: \Facility\Domain\ValueObject\FacilityMetadataFieldType::NUMBER,
        required: true,
        createdAt: new DateTimeImmutable(),
        updatedAt: new DateTimeImmutable(),
      ),
    ]);

    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
      quota: $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactionManager,
      eventDispatcher: $this->createStub(EventDispatcherPort::class),
      metadataSchemaGuard: new FacilityMetadataSchemaGuard($metadataRepository),
    );

    $this->expectException(\Facility\Domain\Exception\FacilityMetadataValidationException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419a4',
      type: 'site',
      name: 'HQ',
      // surface-m2 is required and missing on create.
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOnlyLatitudeIsProvided(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655441950'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655441951',
      type: 'site',
      name: 'HQ',
      latitude: 48.8566,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenOnlyLongitudeIsProvided(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655441960'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655441961',
      type: 'site',
      name: 'HQ',
      longitude: 2.3522,
    ));
  }

  #[Test]
  public function testInvokeReturnsResultWithCoordinates(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-446655441970');
    $organizationId = '550e8400-e29b-41d4-a716-446655441971';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn($generatedId);

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'Paris HQ',
      latitude: 48.8566,
      longitude: 2.3522,
    ));

    self::assertSame(48.8566, $result->latitude);
    self::assertSame(2.3522, $result->longitude);
  }

  #[Test]
  public function testInvokeReturnsResultWithNullCoordinatesWhenOmitted(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-446655441980');
    $organizationId = '550e8400-e29b-41d4-a716-446655441981';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn($generatedId);

    $handler = $this->handler($repository, $uuidFactory);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'HQ Without Coordinates',
    ));

    self::assertNull($result->latitude);
    self::assertNull($result->longitude);
  }

  #[Test]
  public function testInvokeThrowsWhenLatitudeIsOutOfRange(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655441990'));

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility latitude must be between -90 and 90 degrees.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655441991',
      type: 'site',
      name: 'HQ',
      latitude: 90.5,
      longitude: 2.3522,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentFacilityIsArchived(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-446655441940';
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655441941');

    $parentFacility = Facility::create(
      id: $parentId,
      organizationId: new FacilityOrganizationId($organizationId),
      type: FacilityType::SITE,
      name: new FacilityName('Archived Site'),
    );
    $parentFacility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($parentFacility);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory);

    $this->expectException(FacilityArchivedException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655441941" is archived and cannot be used.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'building',
      name: 'Building Under Archived',
      parentFacilityId: (string) $parentId,
    ));
  }

  #[Test]
  public function testInvokeThrowsAndSkipsSaveWhenQuotaExceeded(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419a0'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAdd')
      ->with('550e8400-e29b-41d4-a716-4466554419a1', OrganizationQuotaResource::FACILITIES)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES->value, 2));

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419a1',
      type: 'site',
      name: 'Over Quota HQ',
    ));
  }

  #[Test]
  public function testInvokeReturnsResultWithoutPersistingOnADryRun(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-4466554419a2');
    $organizationId = '550e8400-e29b-41d4-a716-4466554419a3';

    // The negative assertion is the point: a dry run must never reach the
    // repository or the transaction manager.
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($generatedId);

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $quota->expects(self::once())
      ->method('assertProjectedCanAdd')
      ->with($organizationId, OrganizationQuotaResource::FACILITIES, 0);

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'Would-be HQ',
      dryRun: true,
    ));

    self::assertInstanceOf(CreateFacilityResult::class, $result);
    self::assertSame((string) $generatedId, $result->facilityId);
    self::assertSame('active', $result->status);
  }

  #[Test]
  public function testInvokeThrowsQuotaExceededOnADryRunWhenTheProjectedCountReachesTheCap(): void
  {
    $organizationId = '550e8400-e29b-41d4-a716-4466554419a4';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419a5'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAdd');
    $quota->expects(self::once())
      ->method('assertProjectedCanAdd')
      ->with($organizationId, OrganizationQuotaResource::FACILITIES, 2)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES->value, 5));

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'Tips Over The Cap',
      dryRun: true,
      quotaProjectionOffset: 2,
    ));
  }

  #[Test]
  public function testInvokeUsesTheSuppliedResourceIdInsteadOfGeneratingOne(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $result = $this->handler($repository, $uuidFactory)->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419b1',
      type: 'site',
      name: 'Client-Provided Id Site',
      resourceId: '550e8400-e29b-41d4-a716-4466554419b0',
    ));

    self::assertInstanceOf(CreateFacilityResult::class, $result);
    self::assertSame('550e8400-e29b-41d4-a716-4466554419b0', $result->facilityId);
  }

  #[Test]
  public function testInvokeRethrowsUnrecognisedPersistenceFailure(): void
  {
    $handler = $this->handlerFailingWith(new RuntimeException('boom'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419c1',
      type: 'site',
      name: 'Boom Site',
    ));
  }

  #[Test]
  public function testInvokeMapsOrganizationConstraintViolationToInvalidArgument(): void
  {
    $handler = $this->handlerFailingWith(new ForeignKeyConstraintViolationException(
      $this->driverException('SQLSTATE[23503]: insert on table "sites" violates foreign key constraint "fk_facility_organization"'),
      null,
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Organization not found.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419d1',
      type: 'site',
      name: 'Orphan Org Site',
    ));
  }

  #[Test]
  public function testInvokeMapsParentConstraintViolationToFacilityNotFound(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-4466554419e2');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-4466554419e1');

    $parent = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Parent FK Site'),
    );

    $handler = $this->handlerFailingWith(
      new ForeignKeyConstraintViolationException(
        $this->driverException('SQLSTATE[23503]: insert on table "sites" violates foreign key constraint "fk_facility_parent"'),
        null,
      ),
      $parent,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage(sprintf('Facility with ID "%s" not found.', (string) $parentId));

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: (string) $organizationId,
      type: 'building',
      name: 'Orphan Parent Building',
      parentFacilityId: (string) $parentId,
    ));
  }

  #[Test]
  public function testInvokeAllowsParentAtCapMinusOne(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442a01');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655442a02');
    $parentFacility = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Level Seven'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($parentFacility);
    $repository->expects(self::once())->method('depthOf')->with($parentId)->willReturn(7);
    $repository->expects(self::once())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655442a03'));

    $handler = $this->handler($repository, $uuidFactory, maxDepth: 8);

    $result = $handler->__invoke(new CreateFacilityCommand(
      organizationId: (string) $organizationId,
      type: 'building',
      name: 'Level Eight',
      parentFacilityId: (string) $parentId,
    ));

    self::assertInstanceOf(CreateFacilityResult::class, $result);
  }

  #[Test]
  public function testInvokeThrowsWhenParentAtCapWouldExceedMaxDepth(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442a11');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655442a12');
    $parentFacility = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Level Eight'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($parentFacility);
    $repository->expects(self::once())->method('depthOf')->with($parentId)->willReturn(8);
    $repository->expects(self::never())->method('save');

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $handler = $this->handler($repository, $uuidFactory, maxDepth: 8);

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Facility hierarchy depth cap of 8 levels exceeded.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: (string) $organizationId,
      type: 'building',
      name: 'Level Nine',
      parentFacilityId: (string) $parentId,
    ));
  }

  #[Test]
  public function testInvokeDispatchesFacilityCreatedEventAfterSave(): void
  {
    $generatedId = new FacilityId('550e8400-e29b-41d4-a716-4466554419c8');
    $organizationId = '550e8400-e29b-41d4-a716-4466554419c9';

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('save');

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn($generatedId);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof FacilityCreatedEvent
          && $organizationId === $event->organizationId
          && (string) $generatedId === $event->facilityId,
      ));

    $handler = $this->handler($repository, $uuidFactory, eventDispatcher: $eventDispatcher);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'site',
      name: 'Dispatch HQ',
    ));
  }

  #[Test]
  public function testInvokeDoesNotDispatchFacilityCreatedEventWhenSaveFails(): void
  {
    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handlerFailingWith(new RuntimeException('boom'), eventDispatcher: $eventDispatcher);

    $this->expectException(RuntimeException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419ca',
      type: 'site',
      name: 'Failed HQ',
    ));
  }

  private function handlerFailingWith(Throwable $failure, ?Facility $parent = null, ?EventDispatcherPort $eventDispatcher = null): CreateFacilityHandler
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($parent);
    $repository->method('depthOf')->willReturn(0);
    $repository->expects(self::once())->method('save')->willThrowException($failure);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419f0'));

    return $this->handler($repository, $uuidFactory, eventDispatcher: $eventDispatcher);
  }

  private function driverException(string $message): DoctrineDriverException
  {
    return new class ($message) extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23503';
      }
    };
  }

  /**
   * Builds the handler with a pass-through transaction manager (invokes the
   * operation inline) and a permissive quota port unless one is supplied.
   */
  private function handler(
    FacilityRepositoryPort $repository,
    UuidFactory $uuidFactory,
    ?OrganizationQuotaPort $quota = null,
    int $maxDepth = 8,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CreateFacilityHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    $metadataRepository = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $metadataRepository->method('findByOrganizationId')->willReturn([]);

    return new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      metadataSchemaGuard: new FacilityMetadataSchemaGuard($metadataRepository),
      maxDepth: $maxDepth,
    );
  }
}
