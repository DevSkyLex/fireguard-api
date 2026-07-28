<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\UpdateFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\{ForeignKeyConstraintViolationException, UniqueConstraintViolationException};
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\UpdateFacility\{UpdateFacilityCommand, UpdateFacilityHandler, UpdateFacilityResult};
use Facility\Domain\Exception\{FacilityCodeAlreadyExistsException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Throwable;

#[CoversClass(UpdateFacilityHandler::class)]
final class UpdateFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeAppliesOnlyProvidedFieldsForPartialUpdate(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440910'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440911'),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
      code: 'SITE-OLD',
      address: 'Old Address',
      metadata: ['foo' => 'bar'],
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);

    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Facility $saved): bool {
        return 'Main Site Updated' === (string) $saved->name()
          && 'site' === $saved->type()->value
          && 'SITE-OLD' === $saved->code()
          && 'Old Address' === $saved->address()
          && ['foo' => 'bar'] === $saved->metadata();
      }));

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $result = $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440911',
      facilityId: '550e8400-e29b-41d4-a716-446655440910',
      name: 'Main Site Updated',
      hasName: true,
    ));

    self::assertInstanceOf(UpdateFacilityResult::class, $result);
    self::assertSame('Main Site Updated', $result->name);
    self::assertSame('site', $result->type);
    self::assertSame('SITE-OLD', $result->code);
  }

  #[Test]
  public function testInvokeThrowsWhenTypeIsExplicitlyNull(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440920'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440921'),
      type: FacilityType::BUILDING,
      name: new FacilityName('Building A'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);

    $repository->expects(self::never())
      ->method('save');

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Field "type" cannot be null when provided.');

    $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440921',
      facilityId: '550e8400-e29b-41d4-a716-446655440920',
      type: null,
      hasType: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenTypeIsInvalidEnumValue(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440930'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440931'),
      type: FacilityType::BUILDING,
      name: new FacilityName('Building B'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);

    $repository->expects(self::never())
      ->method('save');

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('"invalid_type" is not a valid backing value for enum');

    $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440931',
      facilityId: '550e8400-e29b-41d4-a716-446655440930',
      type: 'invalid_type',
      hasType: true,
    ));
  }

  #[Test]
  public function testInvokeSetsCoordinatesWhenBothProvided(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440940'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440941'),
      type: FacilityType::SITE,
      name: new FacilityName('Paris HQ'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Facility $saved): bool {
        return null !== $saved->coordinates()
          && 48.8566 === $saved->coordinates()->latitude()
          && 2.3522 === $saved->coordinates()->longitude();
      }));

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $result = $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440941',
      facilityId: '550e8400-e29b-41d4-a716-446655440940',
      latitude: 48.8566,
      longitude: 2.3522,
      hasLatitude: true,
      hasLongitude: true,
    ));

    self::assertSame(48.8566, $result->latitude);
    self::assertSame(2.3522, $result->longitude);
  }

  #[Test]
  public function testInvokeClearsCoordinatesWhenBothProvidedAsNull(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440950'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440951'),
      type: FacilityType::SITE,
      name: new FacilityName('Paris HQ'),
      coordinates: new FacilityCoordinates(48.8566, 2.3522),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $saved): bool => null === $saved->coordinates()));

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $result = $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440951',
      facilityId: '550e8400-e29b-41d4-a716-446655440950',
      latitude: null,
      longitude: null,
      hasLatitude: true,
      hasLongitude: true,
    ));

    self::assertNull($result->latitude);
    self::assertNull($result->longitude);
  }

  #[Test]
  public function testInvokeThrowsWhenOnlyLatitudeIsProvided(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440960'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440961'),
      type: FacilityType::SITE,
      name: new FacilityName('Paris HQ'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::never())
      ->method('save');

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440961',
      facilityId: '550e8400-e29b-41d4-a716-446655440960',
      latitude: 48.8566,
      hasLatitude: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenLongitudeProvidedWithNullLatitude(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440970'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440971'),
      type: FacilityType::SITE,
      name: new FacilityName('Paris HQ'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::never())
      ->method('save');

    $handler = new UpdateFacilityHandler(
      facilityRepository: $repository,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    $handler->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440971',
      facilityId: '550e8400-e29b-41d4-a716-446655440970',
      latitude: null,
      longitude: 2.3522,
      hasLatitude: true,
      hasLongitude: true,
    ));
  }

  #[Test]
  public function testInvokeRejectsMalformedIdentifiers(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');

    $this->expectException(InvalidArgumentException::class);

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440981',
      facilityId: 'not-a-uuid',
      name: 'Whatever',
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440980'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440981'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($facility);
    $repository->expects(self::never())->method('save');

    $this->expectException(FacilityNotFoundException::class);

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke(new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440982',
      facilityId: '550e8400-e29b-41d4-a716-446655440980',
      name: 'Renamed',
      hasName: true,
    ));
  }

  #[Test]
  public function testInvokeRejectsExplicitNullName(): void
  {
    $repository = $this->repositoryReturningFacility($this->facility());

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Field "name" cannot be null when provided.');

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke($this->command(name: null, hasName: true));
  }

  #[Test]
  public function testInvokeUpdatesCodeAndAddress(): void
  {
    $facility = $this->facility();
    $repository = $this->repositoryReturningFacility($facility);

    $result = new UpdateFacilityHandler(facilityRepository: $repository)->__invoke($this->command(
      code: 'SITE-NEW',
      hasCode: true,
      address: 'New Address',
      hasAddress: true,
    ));

    self::assertSame('SITE-NEW', $result->code);
    self::assertSame('New Address', $result->address);
  }

  #[Test]
  public function testInvokeMapsDuplicateCodeConstraintViolationToDomainException(): void
  {
    $repository = $this->repositoryFailingWith(new UniqueConstraintViolationException(
      $this->driverException('SQLSTATE[23505]: duplicate key value violates unique constraint "uniq_facility_organization_code"'),
      null,
    ));

    $this->expectException(FacilityCodeAlreadyExistsException::class);

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke($this->command(code: 'SITE-DUP', hasCode: true));
  }

  #[Test]
  public function testInvokeMapsOrganizationConstraintViolationToInvalidArgument(): void
  {
    $repository = $this->repositoryFailingWith(new ForeignKeyConstraintViolationException(
      $this->driverException('SQLSTATE[23503]: update on table "sites" violates foreign key constraint "fk_facility_organization"'),
      null,
    ));

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Organization not found.');

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke($this->command(name: 'Renamed', hasName: true));
  }

  #[Test]
  public function testInvokeRethrowsUnrecognisedPersistenceFailure(): void
  {
    $repository = $this->repositoryFailingWith(new RuntimeException('boom'));

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    new UpdateFacilityHandler(facilityRepository: $repository)->__invoke($this->command(name: 'Renamed', hasName: true));
  }

  private function facility(): Facility
  {
    return Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440990'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440991'),
      type: FacilityType::SITE,
      name: new FacilityName('Persisted Site'),
      code: 'SITE-OLD',
      address: 'Old Address',
    );
  }

  private function command(
    ?string $name = null,
    bool $hasName = false,
    ?string $code = null,
    bool $hasCode = false,
    ?string $address = null,
    bool $hasAddress = false,
  ): UpdateFacilityCommand {
    return new UpdateFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440991',
      facilityId: '550e8400-e29b-41d4-a716-446655440990',
      name: $name,
      code: $code,
      address: $address,
      hasName: $hasName,
      hasCode: $hasCode,
      hasAddress: $hasAddress,
    );
  }

  /**
   * @return FacilityRepositoryPort&MockObject
   */
  private function repositoryReturningFacility(Facility $facility): FacilityRepositoryPort
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findById')->willReturn($facility);

    return $repository;
  }

  /**
   * @return FacilityRepositoryPort&MockObject
   */
  private function repositoryFailingWith(Throwable $failure): FacilityRepositoryPort
  {
    $repository = $this->repositoryReturningFacility($this->facility());
    $repository->expects(self::once())->method('save')->willThrowException($failure);

    return $repository;
  }

  private function driverException(string $message): DoctrineDriverException
  {
    return new class ($message) extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };
  }
}
