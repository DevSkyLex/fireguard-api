<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\RestoreFacility;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\RestoreFacility\{RestoreFacilityCommand, RestoreFacilityHandler, RestoreFacilityResult};
use Facility\Domain\Event\Facility\FacilityRestoredEvent;
use Facility\Domain\Exception\{FacilityArchivedException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(RestoreFacilityHandler::class)]
final class RestoreFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeRestoresArchivedFacilityAndDispatchesRestoredEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655443020');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443021');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building R'),
      code: 'BLDG-R',
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->with($facilityId)
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $saved): bool => FacilityStatus::ACTIVE === $saved->status()));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event) use ($facilityId, $organizationId): bool {
        return $event instanceof FacilityRestoredEvent
          && (string) $organizationId === $event->organizationId
          && (string) $facilityId === $event->facilityId;
      }));

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RestoreFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertInstanceOf(RestoreFacilityResult::class, $result);
    self::assertSame((string) $facilityId, $result->facilityId);
    self::assertSame((string) $organizationId, $result->organizationId);
    self::assertSame('building', $result->type);
    self::assertSame('Building R', $result->name);
    self::assertSame('BLDG-R', $result->code);
    self::assertSame('active', $result->status);
  }

  #[Test]
  public function testInvokeRestoresFacilityUnderActiveParentAndDispatchesRestoredEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655443030');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443031');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655443032');

    $parent = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Parent Site'),
    );

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building Child'),
      parentFacilityId: $parentId,
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->with($facilityId)
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with($parentId)
      ->willReturn($parent);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $saved): bool => FacilityStatus::ACTIVE === $saved->status()));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event) use ($facilityId, $organizationId): bool {
        return $event instanceof FacilityRestoredEvent
          && (string) $organizationId === $event->organizationId
          && (string) $facilityId === $event->facilityId;
      }));

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RestoreFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertInstanceOf(RestoreFacilityResult::class, $result);
    self::assertSame('active', $result->status);
    self::assertSame((string) $parentId, $result->parentFacilityId);
  }

  #[Test]
  public function testInvokeDoesNotDispatchWhenFacilityAlreadyActive(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655443040'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443041'),
      type: FacilityType::SITE,
      name: new FacilityName('Already Active Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $saved): bool => FacilityStatus::ACTIVE === $saved->status()));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RestoreFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443041',
      facilityId: '550e8400-e29b-41d4-a716-446655443040',
    ));

    self::assertInstanceOf(RestoreFacilityResult::class, $result);
    self::assertSame('active', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFoundAndDispatchesNothing(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655443050" not found.');

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443051',
      facilityId: '550e8400-e29b-41d4-a716-446655443050',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganizationAndDispatchesNothing(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655443060'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443061'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655443060" not found.');

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443062',
      facilityId: '550e8400-e29b-41d4-a716-446655443060',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentNotFoundAndDispatchesNothing(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655443072');

    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655443070'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443071'),
      type: FacilityType::BUILDING,
      name: new FacilityName('Orphan Child'),
      parentFacilityId: $parentId,
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with($parentId)
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655443072" not found.');

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443071',
      facilityId: '550e8400-e29b-41d4-a716-446655443070',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentArchivedAndDispatchesNothing(): void
  {
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655443082');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443081');

    $parent = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Archived Parent'),
    );
    $parent->archive();

    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655443080'),
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Blocked Child'),
      parentFacilityId: $parentId,
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with($parentId)
      ->willReturn($parent);
    $repository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(FacilityArchivedException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655443082" is archived and cannot be used.');

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: '550e8400-e29b-41d4-a716-446655443080',
    ));
  }

  #[Test]
  public function testInvokeMapsOrganizationConstraintViolationAndDispatchesNothing(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655443090'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655443091'),
      type: FacilityType::SITE,
      name: new FacilityName('HQ'),
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);

    $driverException = new class ('SQLSTATE[23503]: update on table "facilities" violates foreign key constraint "fk_facility_organization"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23503';
      }
    };

    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new ForeignKeyConstraintViolationException($driverException, null));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Organization not found.');

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655443091',
      facilityId: '550e8400-e29b-41d4-a716-446655443090',
    ));
  }

  #[Test]
  public function testInvokeThrowsInvalidArgumentForMalformedIdentifiersAndDispatchesNothing(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findPublishedById');
    $repository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RestoreFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new RestoreFacilityCommand(
      organizationId: 'not-a-uuid',
      facilityId: 'also-not-a-uuid',
    ));
  }
}
