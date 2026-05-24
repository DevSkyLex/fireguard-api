<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\ArchiveFacility;

use DateTimeImmutable;
use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\ArchiveFacility\{ArchiveFacilityCommand, ArchiveFacilityHandler, ArchiveFacilityResult};
use Facility\Domain\Exception\FacilityNotFoundException;
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityStatus, FacilityType};
use InvalidArgumentException;
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Notification\Domain\ValueObject\NotificationType;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\LoggerPort;

use function is_string;

#[CoversClass(ArchiveFacilityHandler::class)]
final class ArchiveFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeMapsOrganizationConstraintViolationToInvalidArgument(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655440950'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440951'),
      type: FacilityType::SITE,
      name: new FacilityName('HQ'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
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

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('Organization not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440951',
      facilityId: '550e8400-e29b-41d4-a716-446655440950',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFound(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442000" not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442001',
      facilityId: '550e8400-e29b-41d4-a716-446655442000',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442010'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442011'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('save');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442010" not found.');

    $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442012',
      facilityId: '550e8400-e29b-41d4-a716-446655442010',
    ));
  }

  #[Test]
  public function testInvokeArchivesAndReturnsResult(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442020');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442021');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building C'),
      code: 'BLDG-C',
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $f): bool => FacilityStatus::ARCHIVED === $f->status()));

    $organization = Organization::reconstitute(
      id: new OrganizationId((string) $organizationId),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655442022',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655442022',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static function (SendNotificationRequest $request) use ($facilityId, $organizationId): bool {
        return NotificationType::FACILITY_ARCHIVED === $request->type
          && 'Facility archived' === $request->subject
          && 'Facility Building C has been archived.' === $request->body
          && [NotificationChannel::MERCURE] === $request->channels
          && (string) $organizationId === ($request->payload['organizationId'] ?? null)
          && (string) $facilityId === ($request->payload['facilityId'] ?? null)
          && 'Building C' === ($request->payload['facilityName'] ?? null)
          && 'building' === ($request->payload['facilityType'] ?? null)
          && is_string($request->payload['archivedAt'] ?? null)
          && '550e8400-e29b-41d4-a716-446655442022' === $request->recipientUserId;
      }))
      ->willReturn(new SentNotification(
        id: '550e8400-e29b-41d4-a716-446655449030',
        type: NotificationType::FACILITY_ARCHIVED,
        subject: 'Facility archived',
        body: 'Facility Building C has been archived.',
        channels: [NotificationChannel::MERCURE->value],
        payload: ['organizationId' => (string) $organizationId],
        channelDelivery: [NotificationChannel::MERCURE->value => true],
        createdAt: new DateTimeImmutable('2024-01-01T00:00:00+00:00'),
        recipientUserId: '550e8400-e29b-41d4-a716-446655442022',
      ));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $result = $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
    ));

    self::assertInstanceOf(ArchiveFacilityResult::class, $result);
    self::assertSame((string) $facilityId, $result->facilityId);
    self::assertSame((string) $organizationId, $result->organizationId);
    self::assertSame('building', $result->type);
    self::assertSame('Building C', $result->name);
    self::assertSame('BLDG-C', $result->code);
    self::assertSame('archived', $result->status);
  }

  #[Test]
  public function testInvokeReturnsResultWhenNotificationDispatchFails(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442023'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442024'),
      type: FacilityType::SITE,
      name: new FacilityName('Main Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::isInstanceOf(Facility::class));

    $organization = Organization::reconstitute(
      id: new OrganizationId('550e8400-e29b-41d4-a716-446655442024'),
      name: new OrganizationName('Fireguard HQ'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655442025',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
      updatedAt: new DateTimeImmutable('-1 day'),
      ownerUserId: '550e8400-e29b-41d4-a716-446655442025',
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->willThrowException(new RuntimeException('Mercure hub unavailable.'));

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::once())
      ->method('warning')
      ->with(
        'Facility archived notification dispatch failed.',
        [
          'organizationId' => '550e8400-e29b-41d4-a716-446655442024',
          'facilityId' => '550e8400-e29b-41d4-a716-446655442023',
          'recipientUserId' => '550e8400-e29b-41d4-a716-446655442025',
          'error' => 'Mercure hub unavailable.',
        ],
      );

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $result = $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442024',
      facilityId: '550e8400-e29b-41d4-a716-446655442023',
    ));

    self::assertInstanceOf(ArchiveFacilityResult::class, $result);
    self::assertSame('archived', $result->status);
  }

  #[Test]
  public function testInvokeDoesNotNotifyWhenFacilityAlreadyArchived(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442026'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442027'),
      type: FacilityType::SITE,
      name: new FacilityName('Archived Site'),
    );
    $facility->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $savedFacility): bool => FacilityStatus::ARCHIVED === $savedFacility->status()));

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::never())->method('findById');

    /** @var NotificationPort&MockObject $notificationPort */
    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::never())->method('send');

    /** @var LoggerPort&MockObject $logger */
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('warning');

    $handler = new ArchiveFacilityHandler(
      facilityRepository: $repository,
      organizationRepository: $organizationRepository,
      notificationPort: $notificationPort,
      logger: $logger,
    );

    $result = $handler->__invoke(new ArchiveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442027',
      facilityId: '550e8400-e29b-41d4-a716-446655442026',
    ));

    self::assertInstanceOf(ArchiveFacilityResult::class, $result);
    self::assertSame('archived', $result->status);
  }
}
