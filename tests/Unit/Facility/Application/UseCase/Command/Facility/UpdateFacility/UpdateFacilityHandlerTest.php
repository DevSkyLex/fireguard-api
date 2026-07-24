<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\UpdateFacility;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\UpdateFacility\{UpdateFacilityCommand, UpdateFacilityHandler, UpdateFacilityResult};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityCoordinates, FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

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
}
