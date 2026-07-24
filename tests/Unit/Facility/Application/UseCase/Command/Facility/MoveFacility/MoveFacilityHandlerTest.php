<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\MoveFacility;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\MoveFacility\{MoveFacilityCommand, MoveFacilityHandler, MoveFacilityResult};
use Facility\Domain\Event\Facility\FacilityMovedEvent;
use Facility\Domain\Exception\{FacilityArchivedException, FacilityHierarchyException, FacilityNotFoundException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;

#[CoversClass(MoveFacilityHandler::class)]
final class MoveFacilityHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeThrowsWhenParentFacilityIdIsBlankString(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::never())->method('findPublishedById');
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(InvalidArgumentException::class);

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655440941',
      facilityId: '550e8400-e29b-41d4-a716-446655440940',
      parentFacilityId: '',
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenExistingHierarchyContainsCycle(): void
  {
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655440941');
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655440940');
    $parentAId = new FacilityId('550e8400-e29b-41d4-a716-446655440942');
    $parentBId = new FacilityId('550e8400-e29b-41d4-a716-446655440943');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Facility F'),
    );

    $parentA = Facility::create(
      id: $parentAId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Parent A'),
      parentFacilityId: $parentBId,
    );

    $parentB = Facility::create(
      id: $parentBId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Parent B'),
      parentFacilityId: $parentAId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->with(self::callback(static fn (FacilityId $id): bool => $id->equals($facilityId)))
      ->willReturn($facility);
    $repository->expects(self::exactly(2))
      ->method('findById')
      ->willReturnCallback(static function (FacilityId $id) use ($parentAId, $parentBId, $parentA, $parentB): ?Facility {
        if ($id->equals($parentAId)) {
          return $parentA;
        }

        if ($id->equals($parentBId)) {
          return $parentB;
        }

        return null;
      });

    $repository->expects(self::never())
      ->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(
      facilityRepository: $repository,
      eventDispatcher: $eventDispatcher,
    );

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Cannot move facility: hierarchy cycle detected.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $parentAId,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityNotFoundOrDraft(): void
  {
    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn(null);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442100" not found.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442101',
      facilityId: '550e8400-e29b-41d4-a716-446655442100',
      parentFacilityId: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenFacilityBelongsToAnotherOrganization(): void
  {
    $facility = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442110'),
      organizationId: new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442111'),
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442110" not found.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: '550e8400-e29b-41d4-a716-446655442112',
      facilityId: '550e8400-e29b-41d4-a716-446655442110',
      parentFacilityId: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSelfAsParent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442120');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442121');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Self Ref Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('A facility cannot be its own parent.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $facilityId,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentBelongsToAnotherOrganization(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442130');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442131');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655442132');
    $anotherOrgId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442133');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building X'),
    );

    $parentInOtherOrg = Facility::create(
      id: $parentId,
      organizationId: $anotherOrgId,
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Parent'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (FacilityId $id): bool => (string) $id === (string) $parentId))
      ->willReturn($parentInOtherOrg);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(FacilityHierarchyException::class);
    $this->expectExceptionMessage('Parent facility must belong to the same organization.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $parentId,
    ));
  }

  #[Test]
  public function testInvokeMovesSuccessfullyWithNullParentAndDispatchesMovedEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442140');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442141');
    $oldParentId = new FacilityId('550e8400-e29b-41d4-a716-446655442142');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 5'),
      parentFacilityId: $oldParentId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $f): bool => null === $f->parentFacilityId()));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof FacilityMovedEvent
          && (string) $organizationId === $event->organizationId
          && (string) $facilityId === $event->facilityId
          && (string) $oldParentId === $event->previousParentFacilityId
          && null === $event->newParentFacilityId,
      ));

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $result = $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: null,
    ));

    self::assertInstanceOf(MoveFacilityResult::class, $result);
    self::assertSame((string) $facilityId, $result->facilityId);
    self::assertSame((string) $organizationId, $result->organizationId);
    self::assertNull($result->parentFacilityId);
    self::assertSame('floor', $result->type);
    self::assertSame('Floor 5', $result->name);
    self::assertSame('active', $result->status);
  }

  #[Test]
  public function testInvokeMovesToNewParentAndDispatchesMovedEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442160');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442161');
    $oldParentId = new FacilityId('550e8400-e29b-41d4-a716-446655442162');
    $newParentId = new FacilityId('550e8400-e29b-41d4-a716-446655442163');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 7'),
      parentFacilityId: $oldParentId,
    );

    $newParent = Facility::create(
      id: $newParentId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building N'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->with(self::callback(static fn (FacilityId $id): bool => $id->equals($facilityId)))
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (FacilityId $id): bool => $id->equals($newParentId)))
      ->willReturn($newParent);
    $repository->expects(self::once())
      ->method('save')
      ->with(self::callback(static fn (Facility $f): bool => null !== $f->parentFacilityId() && $f->parentFacilityId()->equals($newParentId)));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(
        static fn (object $event): bool => $event instanceof FacilityMovedEvent
          && (string) $organizationId === $event->organizationId
          && (string) $facilityId === $event->facilityId
          && (string) $oldParentId === $event->previousParentFacilityId
          && (string) $newParentId === $event->newParentFacilityId,
      ));

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $result = $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $newParentId,
    ));

    self::assertInstanceOf(MoveFacilityResult::class, $result);
    self::assertSame((string) $newParentId, $result->parentFacilityId);
  }

  #[Test]
  public function testInvokeSameParentMoveDoesNotDispatchMovedEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442170');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442171');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655442172');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 9'),
      parentFacilityId: $parentId,
    );

    $parent = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building S'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (FacilityId $id): bool => $id->equals($parentId)))
      ->willReturn($parent);
    $repository->expects(self::once())
      ->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $result = $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $parentId,
    ));

    self::assertInstanceOf(MoveFacilityResult::class, $result);
    self::assertSame((string) $parentId, $result->parentFacilityId);
  }

  #[Test]
  public function testInvokeRootToRootMoveDoesNotDispatchMovedEvent(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442180');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442181');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Root Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::never())->method('findById');
    $repository->expects(self::once())
      ->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $result = $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: null,
    ));

    self::assertInstanceOf(MoveFacilityResult::class, $result);
    self::assertNull($result->parentFacilityId);
  }

  #[Test]
  public function testInvokeFailedSaveDispatchesNothing(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442190');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442191');
    $oldParentId = new FacilityId('550e8400-e29b-41d4-a716-446655442192');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor 11'),
      parentFacilityId: $oldParentId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('save')
      ->willThrowException(new RuntimeException('boom'));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('boom');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: null,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenParentFacilityIsArchived(): void
  {
    $facilityId = new FacilityId('550e8400-e29b-41d4-a716-446655442150');
    $organizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442151');
    $parentId = new FacilityId('550e8400-e29b-41d4-a716-446655442152');

    $facility = Facility::create(
      id: $facilityId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building Y'),
    );

    $archivedParent = Facility::create(
      id: $parentId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Archived Parent'),
    );
    $archivedParent->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())
      ->method('findPublishedById')
      ->willReturn($facility);
    $repository->expects(self::once())
      ->method('findById')
      ->with(self::callback(static fn (FacilityId $id): bool => (string) $id === (string) $parentId))
      ->willReturn($archivedParent);
    $repository->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = new MoveFacilityHandler(facilityRepository: $repository, eventDispatcher: $eventDispatcher);

    $this->expectException(FacilityArchivedException::class);
    $this->expectExceptionMessage('Facility with ID "550e8400-e29b-41d4-a716-446655442152" is archived and cannot be used.');

    $handler->__invoke(new MoveFacilityCommand(
      organizationId: (string) $organizationId,
      facilityId: (string) $facilityId,
      parentFacilityId: (string) $parentId,
    ));
  }
}
