<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\ORM\{EntityManagerInterface, EntityRepository};
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityMovedEvent, FacilityRestoredEvent};
use Facility\Domain\Exception\FacilityHasActiveDependentsException;
use Facility\Infrastructure\Persistence\Doctrine\Record\FacilityRecord;
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Processor\Facility\CanonicalFacilityMutationProcessor;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\EventDispatcherPort;
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{ConflictHttpException, UnprocessableEntityHttpException};

#[CoversClass(CanonicalFacilityMutationProcessor::class)]
final class CanonicalFacilityMutationProcessorTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440011';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440012';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440013';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440014';

  #[Test]
  public function testDeletingPublishedFacilityArchivesIt(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
    self::assertSame('archived', $record->status);
    self::assertSame(4, $record->revision);
  }

  #[Test]
  public function testDeletingPublishedFacilityWithActiveDependentsIsRefused(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $guard = $this->createMock(FacilityArchivalGuardPort::class);
    $guard->expects(self::once())
      ->method('assertNoActiveDependents')
      ->with(self::ORGANIZATION_ID, self::FACILITY_ID)
      ->willThrowException(FacilityHasActiveDependentsException::withActiveChildFacilities(self::FACILITY_ID));

    $this->expectException(FacilityHasActiveDependentsException::class);

    $this->processor($record, $this->request('DELETE'), $entityManager, $guard)
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDeletingAlreadyArchivedFacilityIsIdempotent(): void
  {
    // A repeat DELETE must not re-run the dependents guard (dependents attached
    // after archival would turn a retry into a spurious 409) nor bump the revision.
    $record = $this->record();
    $record->status = 'archived';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $guard = $this->createMock(FacilityArchivalGuardPort::class);
    $guard->expects(self::never())->method('assertNoActiveDependents');

    $result = $this->processor($record, $this->request('DELETE'), $entityManager, $guard)
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
    self::assertSame('archived', $record->status);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testRefusesDeletingDraftFacilityWithChildren(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';

    $repository = $this->createStub(EntityRepository::class);
    $repository->method('count')->willReturn(1);

    $entityManager = $this->entityManager($record);
    $entityManager->method('getRepository')->willReturn($repository);
    $entityManager->expects(self::never())->method('remove');

    $this->expectException(ConflictHttpException::class);

    $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDeletesDraftFacilityWithoutChildren(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';

    $repository = $this->createStub(EntityRepository::class);
    $repository->method('count')->willReturn(0);

    $entityManager = $this->entityManager($record);
    $entityManager->method('getRepository')->willReturn($repository);
    $entityManager->expects(self::once())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
  }

  #[Test]
  public function testPatchThrowsWhenOnlyLatitudeIsProvided(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->latitude = 48.8566;

    $request = Request::create(
      uri: '/api/facilities/' . self::FACILITY_ID,
      method: 'PATCH',
      content: '{"latitude":48.8566}',
    );
    $request->headers->set('If-Match', '"revision-3"');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Facility latitude and longitude must be provided together.');

    $this->processor($record, $requestStack, $entityManager)->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testPatchSetsBothCoordinatesWhenProvidedTogether(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->latitude = 48.8566;
    $input->longitude = 2.3522;

    $request = Request::create(
      uri: '/api/facilities/' . self::FACILITY_ID,
      method: 'PATCH',
      content: '{"latitude":48.8566,"longitude":2.3522}',
    );
    $request->headers->set('If-Match', '"revision-3"');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->processor($record, $requestStack, $entityManager)->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame(48.8566, $record->latitude);
    self::assertSame(2.3522, $record->longitude);
  }

  #[Test]
  public function testRejectsParentAssignmentThatWouldCreateCycle(): void
  {
    $record = $this->record();
    $parent = $this->record();
    $parent->id = self::PARENT_ID;
    // The proposed parent is itself a child of the record -> assigning it as the
    // record's parent would create a cycle.
    $parent->parentFacility = $record;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $className, string $id): ?FacilityRecord => match ($id) {
        self::FACILITY_ID => $record,
        self::PARENT_ID => $parent,
        default => null,
      },
    );
    $entityManager->expects(self::never())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $request = Request::create(
      uri: '/api/facilities/' . self::FACILITY_ID,
      method: 'PATCH',
      content: '{"parent":"/api/facilities/' . self::PARENT_ID . '"}',
    );
    $request->headers->set('If-Match', '"revision-3"');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('hierarchy cycle');

    $this->processor($record, $requestStack, $entityManager)->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRejectsRestoringFacilityUnderArchivedParent(): void
  {
    $record = $this->record();
    $record->status = 'archived';
    $parent = $this->record();
    $parent->id = self::PARENT_ID;
    $parent->status = 'archived';
    $record->parentFacility = $parent;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->status = 'active';

    $request = Request::create(
      uri: '/api/facilities/' . self::FACILITY_ID,
      method: 'PATCH',
      content: '{"status":"active"}',
    );
    $request->headers->set('If-Match', '"revision-3"');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('parent is archived');

    $this->processor($record, $requestStack, $entityManager)->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testAllowsRestoringFacilityWithoutArchivedParent(): void
  {
    $record = $this->record();
    $record->status = 'archived';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->status = 'active';

    $request = Request::create(
      uri: '/api/facilities/' . self::FACILITY_ID,
      method: 'PATCH',
      content: '{"status":"active"}',
    );
    $request->headers->set('If-Match', '"revision-3"');
    $requestStack = new RequestStack();
    $requestStack->push($request);

    $this->processor($record, $requestStack, $entityManager)->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('active', $record->status);
    self::assertSame(4, $record->revision);
  }

  #[Test]
  public function testDeletingPublishedFacilityDispatchesArchivedEvent(): void
  {
    // Audit ledger: archiving a published facility through the canonical
    // DELETE emits a FacilityArchivedEvent.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityArchivedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId,
    ));

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
    self::assertSame('archived', $record->status);
  }

  #[Test]
  public function testArchivingPublishedFacilityViaPatchDispatchesArchivedEvent(): void
  {
    // Audit ledger: a published active -> archived status PATCH emits a
    // FacilityArchivedEvent, mirroring the DELETE surface.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityArchivedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId,
    ));

    $input = new PatchCanonicalFacilityInput();
    $input->status = 'archived';

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"archived"}'),
      eventDispatcher: $eventDispatcher,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('archived', $record->status);
  }

  #[Test]
  public function testRestoringPublishedFacilityDispatchesRestoredEvent(): void
  {
    // Audit ledger: a published archived -> active status PATCH (restore)
    // emits a FacilityRestoredEvent.
    $record = $this->record();
    $record->status = 'archived';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityRestoredEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId,
    ));

    $input = new PatchCanonicalFacilityInput();
    $input->status = 'active';

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"active"}'),
      eventDispatcher: $eventDispatcher,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('active', $record->status);
  }

  #[Test]
  public function testReparentingPublishedFacilityDispatchesMovedEvent(): void
  {
    // Audit ledger: an actual parent change (here root -> PARENT_ID) emits a
    // FacilityMovedEvent carrying the parent id before and after the move.
    $record = $this->record();
    $parent = $this->record();
    $parent->id = self::PARENT_ID;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $className, string $id): ?FacilityRecord => match ($id) {
        self::FACILITY_ID => $record,
        self::PARENT_ID => $parent,
        default => null,
      },
    );
    $entityManager->expects(self::once())->method('flush');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityMovedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId
        && null === $event->previousParentFacilityId
        && self::PARENT_ID === $event->newParentFacilityId,
    ));

    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $this->processor(
      $record,
      $this->request('PATCH', '{"parent":"/api/facilities/' . self::PARENT_ID . '"}'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame($parent, $record->parentFacility);
  }

  #[Test]
  public function testReparentingToSameParentDispatchesNothing(): void
  {
    // Moving a facility to the parent it already has is an unchanged value:
    // no FacilityMovedEvent may reach the ledger.
    $record = $this->record();
    $parent = $this->record();
    $parent->id = self::PARENT_ID;
    $record->parentFacility = $parent;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $className, string $id): ?FacilityRecord => match ($id) {
        self::FACILITY_ID => $record,
        self::PARENT_ID => $parent,
        default => null,
      },
    );
    $entityManager->expects(self::once())->method('flush');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $this->processor(
      $record,
      $this->request('PATCH', '{"parent":"/api/facilities/' . self::PARENT_ID . '"}'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRepeatDeleteOnArchivedFacilityDispatchesNothing(): void
  {
    // The idempotent repeat DELETE leaves the record untouched: no ledger row.
    $record = $this->record();
    $record->status = 'archived';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDeletingDraftFacilityDispatchesNothing(): void
  {
    // A draft (intervention scratchpad) hard-delete never reaches the ledger.
    $record = $this->record();
    $record->recordStatus = 'draft';

    $repository = $this->createStub(EntityRepository::class);
    $repository->method('count')->willReturn(0);

    $entityManager = $this->entityManager($record);
    $entityManager->method('getRepository')->willReturn($repository);
    $entityManager->expects(self::once())->method('remove');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testPatchingDraftFacilityDispatchesNothing(): void
  {
    // Draft scratchpad edits are not audited, even on a status change.
    $record = $this->record();
    $record->recordStatus = 'draft';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $input = new PatchCanonicalFacilityInput();
    $input->status = 'archived';

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"archived"}'),
      eventDispatcher: $eventDispatcher,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('archived', $record->status);
  }

  #[Test]
  public function testRolledBackMutationDispatchesNothing(): void
  {
    // The audit events are collected during the mutation but dispatched only
    // after the transaction commits: a commit failure (rollback) must leave
    // no ledger row — the ledger is append-only and hash-chained, so a
    // phantom entry could never be removed.
    $record = $this->record();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static function (callable $callback): mixed {
        $callback();

        throw new RuntimeException('commit failed');
      },
    );
    $entityManager->method('find')->with(FacilityRecord::class, self::FACILITY_ID)->willReturn($record);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(RuntimeException::class);

    $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  private function processor(
    FacilityRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
    ?FacilityArchivalGuardPort $archivalGuard = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CanonicalFacilityMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $archivalGuard ??= $this->createStub(FacilityArchivalGuardPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class));
    $provider = new CanonicalFacilityProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalFacilityMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $provider,
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $archivalGuard,
      $eventDispatcher,
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManager(FacilityRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(FacilityRecord::class, self::FACILITY_ID)->willReturn($record);

    return $entityManager;
  }

  private function record(): FacilityRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new FacilityRecord();
    $record->id = self::FACILITY_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->type = 'site';
    $record->name = 'HQ';
    $record->status = 'active';
    $record->createdAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');
    $record->updatedAt = new DateTimeImmutable('2026-02-12T10:00:00+00:00');

    return $record;
  }

  private function request(string $method, ?string $content = null): RequestStack
  {
    $request = Request::create('/api/facilities/' . self::FACILITY_ID, $method, [], [], [], [], $content);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
