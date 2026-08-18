<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree;

use Facility\Application\Port\Outbound\FacilityRepositoryPort;
use Facility\Application\UseCase\Command\Facility\DuplicateFacilitySubtree\{
  DuplicateFacilitySubtreeCommand,
  DuplicateFacilitySubtreeHandler,
  DuplicateFacilitySubtreeResult
};
use Facility\Domain\Event\Facility\FacilitySubtreeDuplicatedEvent;
use Facility\Domain\Exception\{FacilityNotFoundException, FacilitySubtreeSourceArchivedException, FacilitySubtreeTooLargeException};
use Facility\Domain\Model\Facility\Facility;
use Facility\Domain\ValueObject\{FacilityId, FacilityName, FacilityOrganizationId, FacilityType};
use Organization\Application\Contract\Quota\OrganizationQuotaExceededException;
use Organization\Application\Port\Inbound\OrganizationQuotaPort;
use Organization\Domain\ValueObject\OrganizationQuotaResource;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};

use function array_fill;

#[CoversClass(DuplicateFacilitySubtreeHandler::class)]
final class DuplicateFacilitySubtreeHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655442000';

  private const string SOURCE_ID = '550e8400-e29b-41d4-a716-446655442001';

  #[Test]
  public function testInvokeClonesAnActiveSubtreeSkippingArchivedDescendants(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORGANIZATION_ID);
    $sourceId = new FacilityId(self::SOURCE_ID);

    $source = Facility::create(
      id: $sourceId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('HQ Site'),
      code: 'HQ-01',
    );

    $child1Id = new FacilityId('550e8400-e29b-41d4-a716-446655442002');
    $child1 = Facility::create(
      id: $child1Id,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Building A'),
      parentFacilityId: $sourceId,
      code: 'BLDG-A',
    );

    $archivedChildId = new FacilityId('550e8400-e29b-41d4-a716-446655442003');
    $archivedChild = Facility::create(
      id: $archivedChildId,
      organizationId: $organizationId,
      type: FacilityType::BUILDING,
      name: new FacilityName('Archived Building'),
      parentFacilityId: $sourceId,
    );
    $archivedChild->archive();

    $grandchild = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442004'),
      organizationId: $organizationId,
      type: FacilityType::FLOOR,
      name: new FacilityName('Floor Under Archived'),
      parentFacilityId: $archivedChildId,
    );

    $rootCloneId = new FacilityId('550e8400-e29b-41d4-a716-446655442010');
    $child1CloneId = new FacilityId('550e8400-e29b-41d4-a716-446655442011');
    $grandchildCloneId = new FacilityId('550e8400-e29b-41d4-a716-446655442012');

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->with($sourceId)->willReturn($source);
    $repository->expects(self::once())
      ->method('findDescendants')
      ->willReturn([$child1, $archivedChild, $grandchild]);

    /** @var list<Facility> $saved */
    $saved = [];
    $repository->expects(self::exactly(3))
      ->method('save')
      ->willReturnCallback(static function (Facility $facility) use (&$saved): void {
        $saved[] = $facility;
      });

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::exactly(3))
      ->method('create')
      ->with(FacilityId::class)
      ->willReturnOnConsecutiveCalls($rootCloneId, $child1CloneId, $grandchildCloneId);

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAddMultiple')
      ->with(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES, 3);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (FacilitySubtreeDuplicatedEvent $event) use ($rootCloneId): bool {
        return self::ORGANIZATION_ID === $event->organizationId
          && self::SOURCE_ID === $event->sourceFacilityId
          && (string) $rootCloneId === $event->newRootFacilityId
          && 3 === $event->nodeCount;
      }));

    $handler = $this->handler($repository, $uuidFactory, $quota, $eventDispatcher);

    $result = $handler->__invoke(new DuplicateFacilitySubtreeCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::SOURCE_ID,
    ));

    self::assertInstanceOf(DuplicateFacilitySubtreeResult::class, $result);
    self::assertSame((string) $rootCloneId, $result->facilityId);
    self::assertNull($result->parentFacilityId);
    self::assertSame('HQ Site (copy)', $result->name);
    self::assertNull($result->code);
    self::assertSame('active', $result->status);
    self::assertSame(3, $result->nodeCount);

    self::assertCount(3, $saved);

    [$rootClone, $child1Clone, $grandchildClone] = $saved;

    self::assertNull($rootClone->parentFacilityId());
    self::assertSame('HQ Site (copy)', (string) $rootClone->name());
    self::assertNull($rootClone->code());

    self::assertSame((string) $rootCloneId, $child1Clone->parentFacilityId()?->__toString());
    self::assertSame('Building A', (string) $child1Clone->name());
    self::assertNull($child1Clone->code());

    // The archived child was skipped: its live child is reattached to the
    // new root rather than orphaned or dropped.
    self::assertSame((string) $rootCloneId, $grandchildClone->parentFacilityId()?->__toString());
    self::assertSame('Floor Under Archived', (string) $grandchildClone->name());
  }

  #[Test]
  public function testInvokeThrowsWhenSourceIsArchived(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORGANIZATION_ID);
    $sourceId = new FacilityId(self::SOURCE_ID);

    $source = Facility::create(
      id: $sourceId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Archived Site'),
    );
    $source->archive();

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->willReturn($source);
    $repository->expects(self::never())->method('findDescendants');
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(FacilitySubtreeSourceArchivedException::class);

    $handler->__invoke(new DuplicateFacilitySubtreeCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::SOURCE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsAndSkipsAllSavesWhenQuotaIsExceeded(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORGANIZATION_ID);
    $sourceId = new FacilityId(self::SOURCE_ID);

    $source = Facility::create(
      id: $sourceId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Quota Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->willReturn($source);
    $repository->expects(self::once())->method('findDescendants')->willReturn([]);
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::once())
      ->method('create')
      ->with(FacilityId::class)
      ->willReturn(new FacilityId('550e8400-e29b-41d4-a716-446655442020'));

    /** @var OrganizationQuotaPort&MockObject $quota */
    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::once())
      ->method('assertCanAddMultiple')
      ->with(self::ORGANIZATION_ID, OrganizationQuotaResource::FACILITIES, 1)
      ->willThrowException(OrganizationQuotaExceededException::forResource(OrganizationQuotaResource::FACILITIES->value, 1));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $handler = $this->handler($repository, $uuidFactory, $quota, $eventDispatcher);

    $this->expectException(OrganizationQuotaExceededException::class);

    $handler->__invoke(new DuplicateFacilitySubtreeCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::SOURCE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsWhenSubtreeExceedsTheSizeCap(): void
  {
    $organizationId = new FacilityOrganizationId(self::ORGANIZATION_ID);
    $sourceId = new FacilityId(self::SOURCE_ID);

    $source = Facility::create(
      id: $sourceId,
      organizationId: $organizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Huge Site'),
    );

    $filler = Facility::create(
      id: new FacilityId('550e8400-e29b-41d4-a716-446655442030'),
      organizationId: $organizationId,
      type: FacilityType::AREA,
      name: new FacilityName('Filler'),
      parentFacilityId: $sourceId,
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->willReturn($source);
    // 500 descendants + the source itself = 501 nodes, over the 500 cap.
    $repository->expects(self::once())->method('findDescendants')->willReturn(array_fill(0, 500, $filler));
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(FacilitySubtreeTooLargeException::class);

    $handler->__invoke(new DuplicateFacilitySubtreeCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::SOURCE_ID,
    ));
  }

  #[Test]
  public function testInvokeThrowsFacilityNotFoundWhenSourceBelongsToAnotherOrganization(): void
  {
    $anotherOrganizationId = new FacilityOrganizationId('550e8400-e29b-41d4-a716-446655442099');
    $sourceId = new FacilityId(self::SOURCE_ID);

    $source = Facility::create(
      id: $sourceId,
      organizationId: $anotherOrganizationId,
      type: FacilityType::SITE,
      name: new FacilityName('Other Org Site'),
    );

    /** @var FacilityRepositoryPort&MockObject $repository */
    $repository = $this->createMock(FacilityRepositoryPort::class);
    $repository->expects(self::once())->method('findPublishedById')->willReturn($source);
    $repository->expects(self::never())->method('findDescendants');
    $repository->expects(self::never())->method('save');

    $uuidFactory = $this->createMock(UuidFactory::class);
    $uuidFactory->expects(self::never())->method('create');

    $quota = $this->createMock(OrganizationQuotaPort::class);
    $quota->expects(self::never())->method('assertCanAddMultiple');

    $handler = $this->handler($repository, $uuidFactory, $quota);

    $this->expectException(FacilityNotFoundException::class);

    $handler->__invoke(new DuplicateFacilitySubtreeCommand(
      organizationId: self::ORGANIZATION_ID,
      facilityId: self::SOURCE_ID,
    ));
  }

  private function handler(
    FacilityRepositoryPort $repository,
    UuidFactory $uuidFactory,
    ?OrganizationQuotaPort $quota = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): DuplicateFacilitySubtreeHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new DuplicateFacilitySubtreeHandler(
      facilityRepository: $repository,
      uuidFactory: $uuidFactory,
      quota: $quota ?? $this->createStub(OrganizationQuotaPort::class),
      transactionManager: $transactionManager,
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
    );
  }
}
