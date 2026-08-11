<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\SuspendOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\SuspendOrganization\{SuspendOrganizationCommand, SuspendOrganizationHandler, SuspendOrganizationResult};
use Organization\Domain\Event\Organization\OrganizationSuspendedEvent;
use Organization\Domain\Exception\{OrganizationArchivedException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(SuspendOrganizationHandler::class)]
final class SuspendOrganizationHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440600';

  private const string ACTING_USER_ID = '550e8400-e29b-41d4-a716-446655440601';

  #[Test]
  public function testInvokeSuspendsAnActiveOrganization(): void
  {
    $organization = $this->createOrganization(OrganizationStatus::ACTIVE);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::once())
      ->method('save')
      ->with($organization);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationSuspendedEvent
          && self::ORGANIZATION_ID === $event->organizationId,
      ));

    $handler = new SuspendOrganizationHandler(
      organizationRepository: $organizationRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new SuspendOrganizationCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));

    self::assertInstanceOf(SuspendOrganizationResult::class, $result);
    self::assertSame(self::ORGANIZATION_ID, $result->organizationId);
    self::assertSame('suspended', $result->status);
    self::assertFalse($organization->status()->isActive());
  }

  #[Test]
  public function testInvokeIsIdempotentWhenAlreadySuspended(): void
  {
    $organization = $this->createOrganization(OrganizationStatus::SUSPENDED);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SuspendOrganizationHandler(
      organizationRepository: $organizationRepository,
      eventDispatcher: $eventDispatcher,
    );

    $result = $handler->__invoke(new SuspendOrganizationCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));

    self::assertSame('suspended', $result->status);
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationIsArchived(): void
  {
    $organization = $this->createOrganization(OrganizationStatus::ARCHIVED);

    /** @var OrganizationRepositoryPort&MockObject $organizationRepository */
    $organizationRepository = $this->createMock(OrganizationRepositoryPort::class);
    $organizationRepository->expects(self::once())
      ->method('findById')
      ->willReturn($organization);
    $organizationRepository->expects(self::never())->method('save');

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SuspendOrganizationHandler(
      organizationRepository: $organizationRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationArchivedException::class);

    $handler->__invoke(new SuspendOrganizationCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
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

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new SuspendOrganizationHandler(
      organizationRepository: $organizationRepository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new SuspendOrganizationCommand(self::ORGANIZATION_ID, self::ACTING_USER_ID));
  }

  private function createOrganization(OrganizationStatus $status): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORGANIZATION_ID),
      name: new OrganizationName('Fireguard Nantes'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: OrganizationStatus::ACTIVE === $status,
      createdAt: new DateTimeImmutable('-10 days'),
      status: $status,
    );
  }
}
