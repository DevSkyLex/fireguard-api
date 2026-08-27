<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo\{RemoveOrganizationLogoCommand, RemoveOrganizationLogoHandler, RemoveOrganizationLogoResult};
use Organization\Domain\Event\Organization\OrganizationSettingsUpdatedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileStoragePort};

#[CoversClass(RemoveOrganizationLogoHandler::class)]
final class RemoveOrganizationLogoHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440620';

  private const string ACTING_USER_ID = '550e8400-e29b-41d4-a716-446655440621';

  private const string EXPECTED_PATH = 'organization-logos/550e8400-e29b-41d4-a716-446655440620/logo.webp';

  #[Test]
  public function testInvokeDeletesTheStoredLogoAndClearsTheUrl(): void
  {
    $organization = $this->createOrganization(logoUrl: 'https://cdn.example.com/logo.webp');

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with($organization);

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('exists')
      ->with(self::EXPECTED_PATH)
      ->willReturn(true);
    $fileStorage->expects(self::once())
      ->method('delete')
      ->with(self::EXPECTED_PATH);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationSettingsUpdatedEvent
          && self::ORGANIZATION_ID === $event->organizationId
          && ['logo'] === $event->changedFields,
      ));

    $handler = new RemoveOrganizationLogoHandler(
      organizationRepository: $organizationRepository,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));

    self::assertInstanceOf(RemoveOrganizationLogoResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertNull($organization->logoUrl());
  }

  #[Test]
  public function testInvokeSkipsDeleteWhenFileIsAlreadyMissingFromStorage(): void
  {
    $organization = $this->createOrganization(logoUrl: 'https://cdn.example.com/logo.webp');

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::once())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::once())
      ->method('exists')
      ->willReturn(false);
    $fileStorage->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new RemoveOrganizationLogoHandler(
      organizationRepository: $organizationRepository,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $handler->__invoke(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  #[Test]
  public function testInvokeIsIdempotentWhenNoLogoIsSet(): void
  {
    $organization = $this->createOrganization(logoUrl: null);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('exists');
    $fileStorage->expects(self::never())->method('delete');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationLogoHandler(
      organizationRepository: $organizationRepository,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));

    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationIsArchived(): void
  {
    $organization = $this->createOrganization(logoUrl: 'https://cdn.example.com/logo.webp', status: OrganizationStatus::ARCHIVED);

    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->method('findById')->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationLogoHandler(
      organizationRepository: $organizationRepository,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationArchivedException::class);

    $handler->__invoke(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn(null);
    $organizationRepository->expects(self::never())->method('save');

    /** @var FileStoragePort&MockObject $fileStorage */
    $fileStorage = $this->createMock(FileStoragePort::class);
    $fileStorage->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new RemoveOrganizationLogoHandler(
      organizationRepository: $organizationRepository,
      fileStorage: $fileStorage,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  private function createOrganization(?string $logoUrl, OrganizationStatus $status = OrganizationStatus::ACTIVE): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Metz'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: OrganizationStatus::ACTIVE === $status,
      createdAt: new DateTimeImmutable('-10 days'),
      status: $status,
      logoUrl: $logoUrl,
    );
  }
}
