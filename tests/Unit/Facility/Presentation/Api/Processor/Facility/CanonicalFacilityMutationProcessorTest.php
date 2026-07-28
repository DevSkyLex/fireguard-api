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
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
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
use stdClass;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

#[CoversClass(CanonicalFacilityMutationProcessor::class)]
final class CanonicalFacilityMutationProcessorTest extends TestCase
{
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440011';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440012';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440013';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440014';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440015';

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

  #[Test]
  public function testReportsANonStringIdentifierAsNotFound(): void
  {
    $record = $this->record();

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    $this->processor($record, $this->request('PATCH', '{}'))
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => 42]);
  }

  #[Test]
  public function testReportsAnUnknownFacilityAsNotFound(): void
  {
    $record = $this->record();

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturn(null);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(NotFoundHttpException::class);

    $this->processor($record, $this->request('PATCH', '{}'), $entityManager)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRejectsAnUnexpectedInputType(): void
  {
    $record = $this->record();

    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Canonical facility mutation input expected.');

    $this->processor($record, $this->request('PATCH', '{}'))
      ->process(new stdClass(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRejectsANullType(): void
  {
    $this->expectUnprocessable('Facility type cannot be null.', '{"type":null}', new PatchCanonicalFacilityInput());
  }

  #[Test]
  public function testRejectsANullName(): void
  {
    $this->expectUnprocessable('Facility name cannot be null.', '{"name":null}', new PatchCanonicalFacilityInput());
  }

  #[Test]
  public function testRejectsANullStatus(): void
  {
    $this->expectUnprocessable('Facility status cannot be null.', '{"status":null}', new PatchCanonicalFacilityInput());
  }

  #[Test]
  public function testRejectsHalfProvidedCoordinates(): void
  {
    $input = new PatchCanonicalFacilityInput();
    $input->latitude = 48.8566;

    $this->expectUnprocessable(
      'Facility latitude and longitude must be provided together.',
      '{"latitude":48.8566,"longitude":null}',
      $input,
    );
  }

  #[Test]
  public function testAppliesEveryScalarPatchField(): void
  {
    $record = $this->record();

    $input = new PatchCanonicalFacilityInput();
    $input->type = 'building';
    $input->name = '  Tower  ';
    $input->code = '  BLD-1  ';
    $input->address = '  10 rue  ';
    $input->metadata = ['k' => 'v'];
    $input->status = 'active';

    $this->processor($record, $this->request(
      'PATCH',
      '{"type":"building","name":"  Tower  ","code":"  BLD-1  ","address":"  10 rue  ","metadata":{"k":"v"},"status":"active"}',
    ))->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('building', $record->type);
    self::assertSame('Tower', $record->name);
    self::assertSame('BLD-1', $record->code);
    self::assertSame('10 rue', $record->address);
    self::assertSame(['k' => 'v'], $record->metadata);
  }

  #[Test]
  public function testDetachesTheParentOnAnExplicitNull(): void
  {
    $record = $this->record();
    $parent = $this->record();
    $parent->id = self::PARENT_ID;
    $record->parentFacility = $parent;

    $input = new PatchCanonicalFacilityInput();

    $this->processor($record, $this->request('PATCH', '{"parent":null}'))
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertNull($record->parentFacility);
  }

  #[Test]
  public function testRejectsAParentFromAnotherOrganization(): void
  {
    $record = $this->record();
    $foreignParent = new FacilityRecord();
    $foreignParent->id = self::PARENT_ID;

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $class, mixed $id): FacilityRecord => self::FACILITY_ID === $id ? $record : $foreignParent,
    );
    $entityManager->expects(self::never())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Parent facility is invalid.');

    $this->processor($record, $this->request('PATCH', '{"parent":"/api/facilities/' . self::PARENT_ID . '"}'), $entityManager)
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRejectsAnArchivedParentForAPublishedFacility(): void
  {
    $record = $this->record();
    $parent = $this->record();
    $parent->id = self::PARENT_ID;
    $parent->status = 'archived';

    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturnCallback(
      static fn (string $class, mixed $id): FacilityRecord => self::FACILITY_ID === $id ? $record : $parent,
    );
    $entityManager->expects(self::never())->method('flush');

    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage('Parent facility is archived.');

    $this->processor($record, $this->request('PATCH', '{"parent":"/api/facilities/' . self::PARENT_ID . '"}'), $entityManager)
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRequiresAnAuthenticatedSecurityUser(): void
  {
    $record = $this->record();

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->customProcessor($record, $this->request('PATCH', '{}'), authenticated: false)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testRejectsACallerWithoutTheRequiredPermission(): void
  {
    $record = $this->record();

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');

    $this->customProcessor($record, $this->request('PATCH', '{}'), hasPermission: false)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDraftRecordResolvesItsPermissionFromTheIntervention(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );

    $this->customProcessor($record, $this->request('PATCH', '{"code":"DRAFT-1"}'), gateway: $gateway)
      ->process($this->patchCode('DRAFT-1'), new Patch(), ['id' => self::FACILITY_ID]);

    self::assertSame('DRAFT-1', $record->code);
  }

  #[Test]
  public function testDraftRecordReportsAnUnknownInterventionAsNotFound(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->customProcessor($record, $this->request('PATCH', '{}'), gateway: $gateway)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  #[Test]
  public function testDraftRecordReportsAnImmutableInterventionAsConflict(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $gateway = $this->createStub(InterventionResourceGatewayPort::class);
    $gateway->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'submitted'),
    );

    $this->expectException(ConflictHttpException::class);

    $this->customProcessor($record, $this->request('PATCH', '{}'), gateway: $gateway)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);
  }

  private function patchCode(string $code): PatchCanonicalFacilityInput
  {
    $input = new PatchCanonicalFacilityInput();
    $input->code = $code;

    return $input;
  }

  private function expectUnprocessable(string $message, string $content, PatchCanonicalFacilityInput $input): void
  {
    $record = $this->record();

    $this->expectException(UnprocessableEntityHttpException::class);
    $this->expectExceptionMessage($message);

    $this->processor($record, $this->request('PATCH', $content))
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);
  }

  private function customProcessor(
    FacilityRecord $record,
    RequestStack $requestStack,
    bool $hasPermission = true,
    bool $authenticated = true,
    ?InterventionResourceGatewayPort $gateway = null,
  ): CanonicalFacilityMutationProcessor {
    $entityManager = $this->entityManager($record);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($hasPermission);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($authenticated ? $this->user() : null);
    $manager = new InterventionResourceManager($gateway ?? $this->createStub(InterventionResourceGatewayPort::class));

    return new CanonicalFacilityMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      new CanonicalFacilityProvider($entityManager, $authorization, $security, $requestStack, $manager),
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $this->createStub(FacilityArchivalGuardPort::class),
      $this->createStub(EventDispatcherPort::class),
    );
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
