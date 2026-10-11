<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo;

use DateTimeImmutable;
use Organization\Application\Contract\Event\OrganizationSettingsUpdatedEvent;
use Organization\Application\Contract\Exception\OrganizationNotFoundException;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\Internal\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\RemoveOrganizationLogo\{RemoveOrganizationLogoCommand, RemoveOrganizationLogoHandler};
use Organization\Domain\Exception\OrganizationArchivedException;
use Organization\Domain\Model\Organization\{Organization, RestoredOrganizationCore, RestoredOrganizationProfile, RestoredOrganizationState};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{EventDispatcherPort, FileCleanupPort, TransactionManagerPort};

#[CoversClass(RemoveOrganizationLogoHandler::class)]
final class RemoveOrganizationLogoHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440010';

  private const string ACTING_USER_ID = '550e8400-e29b-41d4-a716-446655440001';

  #[Test]
  public function clearsTheUrlAndRecordsCleanupInsideTheMutationTransaction(): void
  {
    $organization = $this->createOrganization('https://app.test/logo.webp?v=bd000000-0000-4000-8000-000000000086');
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->expects(self::once())->method('findByIdForUpdate')->willReturn($organization);
    $organizations->expects(self::once())->method('save')->with($organization);
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::once())->method('request')->with('organization-logos/' . self::ORGANIZATION_ID . '/bd000000-0000-4000-8000-000000000086.webp');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->with(self::callback(static fn (object $event): bool => $event instanceof OrganizationSettingsUpdatedEvent && self::ORGANIZATION_ID === $event->organizationId && ['logo'] === $event->changedFields));
    $result = $this->handler($organizations, $cleanup, $events)(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertNull($organization->logoUrl());
  }

  #[Test]
  public function aRepeatWithNoLogoHasNoStorageOrEventConsequence(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findByIdForUpdate')->willReturn($this->createOrganization(null));
    $organizations->expects(self::never())->method('save');
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->handler($organizations, $cleanup, $events)(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  #[Test]
  public function archivedOrganizationsRefuseRemovalBeforeAnyCleanup(): void
  {
    $organizations = $this->createMock(OrganizationRepositoryPort::class);
    $organizations->method('findByIdForUpdate')->willReturn($this->createOrganization('https://app.test/logo.webp', OrganizationStatus::ARCHIVED));
    $organizations->expects(self::never())->method('save');
    $cleanup = $this->createMock(FileCleanupPort::class);
    $cleanup->expects(self::never())->method('request');
    $this->expectException(OrganizationArchivedException::class);
    $this->handler($organizations, $cleanup, $this->createStub(EventDispatcherPort::class))(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  #[Test]
  public function anAbsentOrganizationRetainsItsNotFoundFailure(): void
  {
    $this->expectException(OrganizationNotFoundException::class);
    $this->handler($this->createStub(OrganizationRepositoryPort::class), $this->createStub(FileCleanupPort::class), $this->createStub(EventDispatcherPort::class))(new RemoveOrganizationLogoCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  private function handler(OrganizationRepositoryPort $organizations, FileCleanupPort $cleanup, EventDispatcherPort $events): RemoveOrganizationLogoHandler
  {
    $transactions = $this->createMock(TransactionManagerPort::class);
    $transactions->expects(self::once())->method('transactional')->willReturnCallback(static fn (callable $work): mixed => $work());
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    return new RemoveOrganizationLogoHandler($organizations, $cleanup, $events, $transactions, $authorization);
  }

  private function createOrganization(?string $logoUrl, OrganizationStatus $status = OrganizationStatus::ACTIVE): Organization
  {
    return Organization::reconstitute(
      core: new RestoredOrganizationCore(
        id: new OrganizationId(self::ORGANIZATION_ID),
        name: new OrganizationName('Fireguard Metz'),
        createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
        isActive: OrganizationStatus::ACTIVE === $status,
        createdAt: new DateTimeImmutable('-10 days'),
      ),
      state: new RestoredOrganizationState(
        status: $status,
      ),
      profile: new RestoredOrganizationProfile(
        logoUrl: $logoUrl,
      ),
    );
  }
}
