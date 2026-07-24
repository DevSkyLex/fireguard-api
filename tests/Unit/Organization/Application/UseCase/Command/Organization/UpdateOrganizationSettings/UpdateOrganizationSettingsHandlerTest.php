<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings;

use Doctrine\DBAL\Driver\Exception as DoctrineDriverException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\UpdateOrganizationSettings\{
  UpdateOrganizationSettingsCommand,
  UpdateOrganizationSettingsHandler,
  UpdateOrganizationSettingsResult
};
use Organization\Domain\Event\Organization\{
  OrganizationRestoredEvent,
  OrganizationSettingsUpdatedEvent,
  OrganizationSuspendedEvent
};
use Organization\Domain\Exception\{
  OrganizationArchivedException,
  OrganizationNotFoundException,
  OrganizationSlugAlreadyExistsException
};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationSlug};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Infrastructure\Exception\TransactionExecutionException;

#[CoversClass(UpdateOrganizationSettingsHandler::class)]
final class UpdateOrganizationSettingsHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string OWNER_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function testInvokeAppliesProvidedFieldsAndSaves(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        return 'Fireguard Paris' === (string) $saved->name()
          && 'fireguard-paris' === (string) $saved->slug()
          && 'HQ in Paris' === $saved->description()
          && false === $saved->isActive();
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var list<object> $dispatchedEvents */
    $dispatchedEvents = [];

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::exactly(2))
      ->method('dispatch')
      ->willReturnCallback(static function (object $event) use (&$dispatchedEvents): void {
        $dispatchedEvents[] = $event;
      });

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
      slug: 'fireguard-paris',
      description: 'HQ in Paris',
      isActive: false,
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);

    [$settingsEvent, $suspendedEvent] = $dispatchedEvents;
    self::assertInstanceOf(OrganizationSettingsUpdatedEvent::class, $settingsEvent);
    self::assertSame(self::ORGANIZATION_ID, $settingsEvent->organizationId);
    self::assertSame(['name', 'slug', 'description'], $settingsEvent->changedFields);
    self::assertInstanceOf(OrganizationSuspendedEvent::class, $suspendedEvent);
    self::assertSame(self::ORGANIZATION_ID, $suspendedEvent->organizationId);
  }

  #[Test]
  public function testInvokeAppliesNotificationAndRegionalSections(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        $settings = $saved->settings();

        // Provided notification flags applied, unspecified ones preserved.
        return false === $settings->notifications->emailEnabled
          && true === $settings->notifications->inAppEnabled
          && 'Europe/Paris' === $settings->regional->timezone
          && 'fr-FR' === $settings->regional->locale
          // Unspecified regional fields keep their defaults.
          && 'metric' === $settings->regional->measurementSystem;
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationSettingsUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && ['notifications', 'regional'] === $event->changedFields;
      }));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      notifications: ['email_enabled' => false, 'in_app_enabled' => null],
      regional: ['timezone' => 'Europe/Paris', 'locale' => 'fr-FR', 'date_format' => null],
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $organizationRepository->expects(self::never())->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugAlreadyExists(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    $driverException = new class ('SQLSTATE[23505]: Unique violation: duplicate key value violates unique constraint "uniq_organization_slug"') extends RuntimeException implements DoctrineDriverException {
      public function getSQLState(): string
      {
        return '23505';
      }
    };

    $uniqueViolation = new UniqueConstraintViolationException($driverException, null);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willThrowException(TransactionExecutionException::wrap($uniqueViolation));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationSlugAlreadyExistsException::class);
    $this->expectExceptionMessage('Organization slug "fireguard-paris" already exists.');

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      slug: 'fireguard-paris',
    ));
  }

  #[Test]
  public function testInvokeDispatchesRestoredEventWithPreviousStatusWhenReactivating(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );
    $organization->archive();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationRestoredEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && 'archived' === $event->previousStatus;
      }));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      isActive: true,
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeDispatchesNoLifecycleEventWhenAlreadyActive(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      isActive: true,
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeRefusesSuspensionOfArchivedOrganization(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );
    $organization->archive();

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::never())->method('transactional');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationArchivedException::class);

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
      isActive: false,
    ));
  }

  #[Test]
  public function testInvokeDispatchesSettingsUpdatedEventWithChangedFields(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationSettingsUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && ['name', 'slug'] === $event->changedFields;
      }));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
      slug: 'fireguard-paris',
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeDispatchesNoSettingsEventWhenTransactionFails(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willThrowException(new RuntimeException('Connection lost during commit.'));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(RuntimeException::class);

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
    ));
  }

  #[Test]
  public function testInvokeAppliesLegalProfileFields(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        return 'FR' === (string) $saved->country()
          && 'limited_liability_company' === $saved->legalType()?->value
          && 'Fireguard Paris SARL' === $saved->legalName()
          && 'RCS PARIS 812345678' === (string) $saved->registrationNumber()
          && 'FR12345678901' === (string) $saved->vatNumber();
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationSettingsUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && ['legal'] === $event->changedFields;
      }));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      country: 'fr',
      legalType: 'limited_liability_company',
      legalName: 'Fireguard Paris SARL',
      registrationNumber: 'RCS PARIS 812345678',
      vatNumber: 'FR12345678901',
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeClearsLegalProfileFieldsWithEmptyStrings(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $saved): bool {
        return null === $saved->country()
          && null === $saved->legalType()
          && null === $saved->legalName()
          && null === $saved->registrationNumber()
          && null === $saved->vatNumber();
      }));

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    $eventDispatcher = $this->createStub(EventDispatcherPort::class);

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      country: '',
      legalType: '',
      legalName: '',
      registrationNumber: '',
      vatNumber: '',
    ));

    self::assertInstanceOf(UpdateOrganizationSettingsResult::class, $result);
  }

  #[Test]
  public function testInvokeLeavesLegalProfileUnchangedWhenNotProvided(): void
  {
    $organization = Organization::create(
      id: OrganizationId::fromString(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Lyon'),
      ownerUserId: self::OWNER_USER_ID,
      slug: new OrganizationSlug('fireguard-lyon'),
    );

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    /** @var TransactionManagerPort&MockObject $transactionManager */
    $transactionManager = $this->createMock(TransactionManagerPort::class);
    $transactionManager->expects(self::once())
      ->method('transactional')
      ->with(self::isCallable())
      ->willReturnCallback(static fn (callable $operation): mixed => $operation());

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (object $event): bool {
        return $event instanceof OrganizationSettingsUpdatedEvent
          && ['name'] === $event->changedFields;
      }));

    $handler = new UpdateOrganizationSettingsHandler(
      organizationRepository: $organizationRepository,
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new UpdateOrganizationSettingsCommand(
      organizationId: self::ORGANIZATION_ID,
      name: 'Fireguard Paris',
    ));

    self::assertNull($organization->country());
    self::assertNull($organization->legalType());
    self::assertNull($organization->legalName());
  }
}
