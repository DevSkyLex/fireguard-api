<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler, CreateFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityCodeAlreadyExistsException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Domain\Exception\OrganizationQuotaExceededException;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\TransactionManagerPort;
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

    $this->expectException(InvalidArgumentException::class);

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

    $this->expectException(InvalidArgumentException::class);
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

    $this->expectException(InvalidArgumentException::class);
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

    $this->expectException(InvalidArgumentException::class);
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
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES, 2));

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-4466554419a1',
      type: 'site',
      name: 'Over Quota HQ',
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

  private function handlerFailingWith(Throwable $failure, ?Facility $parent = null): CreateFacilityHandler
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->method('findById')->willReturn($parent);
    $repository->expects(self::once())->method('save')->willThrowException($failure);

    $uuidFactory = $this->createStub(UuidFactory::class);
    $uuidFactory->method('create')->willReturn(new FacilityId('550e8400-e29b-41d4-a716-4466554419f0'));

    return $this->handler($repository, $uuidFactory);
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
  ): CreateFacilityHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactionManager,
    );
  }
}
