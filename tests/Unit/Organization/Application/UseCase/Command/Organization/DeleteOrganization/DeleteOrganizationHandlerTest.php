<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Organization\DeleteOrganization;

use DateTimeImmutable;
use Organization\Application\Port\Outbound\OrganizationRepositoryPort;
use Organization\Application\UseCase\Command\Organization\DeleteOrganization\{DeleteOrganizationCommand, DeleteOrganizationHandler, DeleteOrganizationResult};
use Organization\Domain\Event\Organization\OrganizationArchivedEvent;
use Organization\Domain\Exception\{OrganizationDeletionConfirmationMismatchException, OrganizationNotFoundException};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName, OrganizationStatus};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(DeleteOrganizationHandler::class)]
final class DeleteOrganizationHandlerTest extends TestCase
{
  private const string ORG_ID = '550e8400-e29b-41d4-a716-446655440400';

  /**
   * The slug OrganizationSlug::fromName() derives from "Fireguard Nice",
   * the display name used by activeOrganization() below.
   */
  private const string ORG_SLUG = 'fireguard-nice';

  #[Test]
  public function testInvokeArchivesOrganizationInsteadOfHardDeleting(): void
  {
    $organization = $this->activeOrganization();

    $savedStatus = null;
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('delete');
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static function (Organization $organization) use (&$savedStatus): bool {
        $savedStatus = $organization->status();

        return true;
      }));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof OrganizationArchivedEvent
          && self::ORG_ID === $event->organizationId,
      ));

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $result = $handler->__invoke(new DeleteOrganizationCommand(
      organizationId: self::ORG_ID,
      slugConfirmation: self::ORG_SLUG,
    ));

    self::assertInstanceOf(DeleteOrganizationResult::class, $result);
    self::assertSame(OrganizationStatus::ARCHIVED, $savedStatus);
    self::assertTrue($organization->isArchived());
  }

  #[Test]
  public function testInvokeAcceptsCaseInsensitiveTrimmedConfirmation(): void
  {
    $organization = $this->activeOrganization();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::once())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $result = $handler->__invoke(new DeleteOrganizationCommand(
      organizationId: self::ORG_ID,
      slugConfirmation: '  Fireguard-Nice  ',
    ));

    self::assertInstanceOf(DeleteOrganizationResult::class, $result);
    self::assertTrue($organization->isArchived());
  }

  #[Test]
  public function testInvokeThrowsWhenOrganizationNotFound(): void
  {
    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn(null);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $this->expectException(OrganizationNotFoundException::class);

    $handler->__invoke(new DeleteOrganizationCommand(
      organizationId: self::ORG_ID,
      slugConfirmation: self::ORG_SLUG,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugConfirmationMissing(): void
  {
    $organization = $this->activeOrganization();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    try {
      $handler->__invoke(new DeleteOrganizationCommand(organizationId: self::ORG_ID, slugConfirmation: null));
    } finally {
      self::assertFalse($organization->isArchived(), 'a missing confirmation must never archive the organization');
    }
  }

  #[Test]
  public function testInvokeThrowsWhenSlugConfirmationIsBlank(): void
  {
    $organization = $this->activeOrganization();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $handler->__invoke(new DeleteOrganizationCommand(organizationId: self::ORG_ID, slugConfirmation: '   '));
  }

  #[Test]
  public function testInvokeThrowsWhenSlugConfirmationMismatched(): void
  {
    $organization = $this->activeOrganization();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    try {
      $handler->__invoke(new DeleteOrganizationCommand(
        organizationId: self::ORG_ID,
        slugConfirmation: 'not-the-right-slug',
      ));
    } finally {
      self::assertFalse($organization->isArchived(), 'a mismatched confirmation must never archive the organization');
    }
  }

  #[Test]
  public function testInvokeIsIdempotentWhenAlreadyArchived(): void
  {
    $organization = $this->activeOrganization();
    $organization->archive();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('save');
    $repository->expects(self::never())->method('delete');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $result = $handler->__invoke(new DeleteOrganizationCommand(
      organizationId: self::ORG_ID,
      slugConfirmation: self::ORG_SLUG,
    ));

    self::assertInstanceOf(DeleteOrganizationResult::class, $result);
  }

  #[Test]
  public function testInvokeStillRequiresConfirmationWhenAlreadyArchived(): void
  {
    $organization = $this->activeOrganization();
    $organization->archive();

    $repository = $this->createMock(OrganizationRepositoryPort::class);
    $repository->method('findById')->willReturn($organization);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new DeleteOrganizationHandler($repository, $eventDispatcher);

    $this->expectException(OrganizationDeletionConfirmationMismatchException::class);

    $handler->__invoke(new DeleteOrganizationCommand(organizationId: self::ORG_ID, slugConfirmation: null));
  }

  private function activeOrganization(): Organization
  {
    return Organization::reconstitute(
      id: new OrganizationId(self::ORG_ID),
      name: new OrganizationName('Fireguard Nice'),
      createdByUserId: '550e8400-e29b-41d4-a716-446655440001',
      isActive: true,
      createdAt: new DateTimeImmutable('-2 days'),
    );
  }
}
