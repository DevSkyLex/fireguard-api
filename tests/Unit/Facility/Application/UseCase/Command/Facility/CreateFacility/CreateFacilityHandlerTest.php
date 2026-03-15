<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\CreateFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\CreateFacility\{CreateFacilityCommand, CreateFacilityHandler, CreateFacilityResult};
use Facility\Domain\Exception\{FacilityArchivedException, FacilityCodeAlreadyExistsException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Factory\UuidFactory;

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

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

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

    /** @var UuidFactory&MockObject $uuidFactory */
    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655440900'));

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

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

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

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

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

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

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

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

    $handler = new CreateFacilityHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
    );

    $this->expectException(FacilityArchivedException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655441941" is archived and cannot be used.');

    $handler->__invoke(new CreateFacilityCommand(
      organizationId: $organizationId,
      type: 'building',
      name: 'Building Under Archived',
      parentFacilityId: (string) $parentId,
    ));
  }
}
