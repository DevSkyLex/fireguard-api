<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility;

use DateTimeImmutable;
use Facility\Application\Port\Inbound\FacilityArchivalGuardPort;
use Facility\Application\Port\Outbound\{CanonicalFacilityRepositoryPort, FacilityMetadataFieldRepositoryPort, FacilityRepositoryPort, InterventionScopePort};
use Facility\Application\Service\FacilityMetadataSchemaGuard;
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\{PatchCanonicalFacilityCommand, PatchCanonicalFacilityHandler};
use Facility\Domain\Event\Facility\{FacilityArchivedEvent, FacilityMovedEvent, FacilityRestoredEvent, FacilityUpdatedEvent};
use Facility\Domain\Exception\{CanonicalFacilityValidationException, FacilityHasActiveDependentsException, FacilityNotFoundException, FacilityRevisionMismatchException};
use Facility\Domain\Model\Facility\CanonicalFacility;
use Facility\Domain\ValueObject\{FacilityId, FacilityOrganizationId, FacilityRecordStatus, FacilityStatus, FacilityType};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Shared\Application\Port\Outbound\{EventDispatcherPort, TransactionManagerPort};
use Shared\Domain\Exception\InvalidValueException;

/**
 * Test PatchCanonicalFacilityHandlerTest.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(PatchCanonicalFacilityHandler::class)]
final class PatchCanonicalFacilityHandlerTest extends TestCase
{
  // #region Constants
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440041';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string OTHER_ORGANIZATION_ID = '550e8400-e29b-41d4-a716-4466554400f2';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440045';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440044';
  // #endregion

  // #region Tests
  /**
   * Method testADescriptivePatchSavesTouchesAndAuditsTheChangedFields.
   *
   * @return void no return value
   */
  #[Test]
  public function testADescriptivePatchSavesTouchesAndAuditsTheChangedFields(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(interventionId: self::INTERVENTION_ID));
    $facilities->expects(self::once())->method('save');
    $interventions = $this->createMock(InterventionScopePort::class);
    $interventions->expects(self::once())->method('touchDraft')->with(self::INTERVENTION_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityUpdatedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::FACILITY_ID === $event->facilityId
        && ['name'] === $event->changedFields,
    ));

    $result = $this->handler($facilities, interventions: $interventions, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasName: true, name: 'Renamed'),
    );

    self::assertSame(['name'], $result->changedFields);
    self::assertSame(4, $result->revision);
  }

  /**
   * Method testResendingTheCurrentValueAuditsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testResendingTheCurrentValueAuditsNothing(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($facilities, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasName: true, name: 'Main site'),
    );

    self::assertSame([], $result->changedFields);
  }

  /**
   * Method testAPatchWithLevelIndexSavesTouchesAndAuditsTheChangedField.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithLevelIndexSavesTouchesAndAuditsTheChangedField(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->expects(self::once())->method('save');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityUpdatedEvent
        && ['levelIndex'] === $event->changedFields,
    ));

    $result = $this->handler($facilities, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasLevelIndex: true, levelIndex: -1),
    );

    self::assertSame(['levelIndex'], $result->changedFields);
  }

  /**
   * Method testAnAbsentLevelIndexKeyLeavesItUnchanged.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentLevelIndexKeyLeavesItUnchanged(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(levelIndex: 4));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityUpdatedEvent
        && ['name'] === $event->changedFields,
    ));

    $this->handler($facilities, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasName: true, name: 'Renamed'),
    );
  }

  /**
   * Method testAnExplicitNullLevelIndexErasesIt.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnExplicitNullLevelIndexErasesIt(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(levelIndex: 4));

    $result = $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasLevelIndex: true, levelIndex: null),
    );

    self::assertSame(['levelIndex'], $result->changedFields);
  }

  /**
   * Method testAPatchWithLevelIndexOutOfRangeIsRejected.
   *
   * The PATCH path must not bypass the domain bound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithLevelIndexOutOfRangeIsRejected(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->expects(self::never())->method('save');

    $this->expectException(InvalidValueException::class);
    $this->expectExceptionMessage('Facility level index must be between -100 and 200.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasLevelIndex: true, levelIndex: 201),
    );
  }

  /**
   * Method testArchivingRunsTheDependencyGuardAndAudits.
   *
   * @return void no return value
   */
  #[Test]
  public function testArchivingRunsTheDependencyGuardAndAudits(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::once())->method('assertNoActiveDependents')
      ->with(self::ORGANIZATION_ID, self::FACILITY_ID);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(FacilityArchivedEvent::class));

    $result = $this->handler($facilities, archivalGuard: $archivalGuard, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasStatus: true, status: 'archived'),
    );

    self::assertTrue($result->archived);
  }

  /**
   * Method testArchivingIsRefusedWhileALiveDependentRemains.
   *
   * @return void no return value
   */
  #[Test]
  public function testArchivingIsRefusedWhileALiveDependentRemains(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->expects(self::never())->method('save');
    $archivalGuard = $this->createStub(FacilityArchivalGuardPort::class);
    $archivalGuard->method('assertNoActiveDependents')
      ->willThrowException(FacilityHasActiveDependentsException::withActiveChildFacilities(self::FACILITY_ID));

    $this->expectException(FacilityHasActiveDependentsException::class);

    $this->handler($facilities, archivalGuard: $archivalGuard)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasStatus: true, status: 'archived'),
    );
  }

  /**
   * Method testRestoringAuditsARestoredEvent.
   *
   * @return void no return value
   */
  #[Test]
  public function testRestoringAuditsARestoredEvent(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(status: FacilityStatus::ARCHIVED));
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::isInstanceOf(FacilityRestoredEvent::class));

    $result = $this->handler($facilities, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasStatus: true, status: 'active'),
    );

    self::assertTrue($result->restored);
  }

  /**
   * Method testRestoringResolvesTheCurrentParentEvenWhenThePatchNeverMentionsIt.
   *
   * The restore guard reads the EFFECTIVE parent. A patch that only flips the
   * status still has to be judged against the parent the facility already
   * hangs from — otherwise a facility comes back to life inside an archived
   * subtree.
   *
   * @return void no return value
   */
  #[Test]
  public function testRestoringResolvesTheCurrentParentEvenWhenThePatchNeverMentionsIt(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id
        ? $this->facility(status: FacilityStatus::ARCHIVED, parentFacilityId: self::PARENT_ID)
        : $this->parent(FacilityStatus::ARCHIVED),
    );

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Cannot restore a facility while its parent is archived.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasStatus: true, status: 'active'),
    );
  }

  /**
   * Method testAParentFromAnotherOrganizationIsRejected.
   *
   * @return void no return value
   */
  #[Test]
  public function testAParentFromAnotherOrganizationIsRejected(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id
        ? $this->facility()
        : $this->parent(organizationId: self::OTHER_ORGANIZATION_ID),
    );

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Parent facility is invalid.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );
  }

  /**
   * Method testAnAbsentParentIsRejectedWithTheSameMessage.
   *
   * One message for "does not exist" and "not yours" on purpose: telling them
   * apart would leak whether a facility id exists in a tenant the caller
   * cannot see.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentParentIsRejectedWithTheSameMessage(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): ?CanonicalFacility => self::FACILITY_ID === (string) $id ? $this->facility() : null,
    );

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Parent facility is invalid.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );
  }

  /**
   * Method testAParentThatIsTheFacilityItselfIsACycle.
   *
   * @return void no return value
   */
  #[Test]
  public function testAParentThatIsTheFacilityItselfIsACycle(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->method('ancestorIdsOf')->willReturn([]);

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Parent facility would create a hierarchy cycle.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::FACILITY_ID),
    );
  }

  /**
   * Method testAParentThatIsADescendantIsACycle.
   *
   * @return void no return value
   */
  #[Test]
  public function testAParentThatIsADescendantIsACycle(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id ? $this->facility() : $this->parent(),
    );
    $facilities->method('ancestorIdsOf')->willReturn([self::FACILITY_ID]);

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Parent facility would create a hierarchy cycle.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );
  }

  /**
   * Method testAnArchivedParentIsRejectedForAPublishedFacility.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnArchivedParentIsRejectedForAPublishedFacility(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id
        ? $this->facility()
        : $this->parent(FacilityStatus::ARCHIVED),
    );
    $facilities->method('ancestorIdsOf')->willReturn([]);

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Parent facility is archived.');

    $this->handler($facilities)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );
  }

  /**
   * Method testAMoveThatExceedsTheDepthCapIsRejected.
   *
   * The cap is measured over the PUBLISHED tree: the parent's depth, plus
   * one, plus whatever sub-tree still hangs beneath the facility.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMoveThatExceedsTheDepthCapIsRejected(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id ? $this->facility() : $this->parent(),
    );
    $facilities->method('ancestorIdsOf')->willReturn([]);
    $facilityRepository = $this->createStub(FacilityRepositoryPort::class);
    $facilityRepository->method('depthOf')->willReturn(7);
    $facilityRepository->method('subtreeHeight')->willReturn(1);

    $this->expectException(CanonicalFacilityValidationException::class);
    $this->expectExceptionMessage('Facility hierarchy depth cap of 8 levels exceeded.');

    $this->handler($facilities, facilityRepository: $facilityRepository)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );
  }

  /**
   * Method testALegalMoveAuditsAMovedEvent.
   *
   * @return void no return value
   */
  #[Test]
  public function testALegalMoveAuditsAMovedEvent(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturnCallback(
      fn (FacilityId $id): CanonicalFacility => self::FACILITY_ID === (string) $id ? $this->facility() : $this->parent(),
    );
    $facilities->method('ancestorIdsOf')->willReturn([]);
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof FacilityMovedEvent
        && null === $event->previousParentFacilityId
        && self::PARENT_ID === $event->newParentFacilityId,
    ));

    $result = $this->handler($facilities, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasParent: true, parentFacilityId: self::PARENT_ID),
    );

    self::assertTrue($result->parentMoved);
  }

  /**
   * Method testAScratchpadPatchAuditsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadPatchAuditsNothing(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility(
      recordStatus: FacilityRecordStatus::DRAFT,
      interventionId: self::INTERVENTION_ID,
    ));
    $archivalGuard = $this->createMock(FacilityArchivalGuardPort::class);
    $archivalGuard->expects(self::never())->method('assertNoActiveDependents');
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');

    $result = $this->handler($facilities, archivalGuard: $archivalGuard, eventDispatcher: $dispatcher)(
      new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasStatus: true, status: 'archived', hasName: true, name: 'Renamed'),
    );

    self::assertFalse($result->archived);
    self::assertSame([], $result->changedFields);
  }

  /**
   * Method testARolledBackMutationAuditsNothing.
   *
   * One patch can produce three ledger rows; a rollback must produce none.
   *
   * @return void no return value
   */
  #[Test]
  public function testARolledBackMutationAuditsNothing(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $dispatcher = $this->createMock(EventDispatcherPort::class);
    $dispatcher->expects(self::never())->method('dispatch');
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static function (callable $operation): mixed {
        $operation();

        throw new RuntimeException('commit failed');
      },
    );

    $this->expectException(RuntimeException::class);

    $handler = new PatchCanonicalFacilityHandler(
      facilities: $facilities,
      facilityRepository: $this->createStub(FacilityRepositoryPort::class),
      metadataSchemaGuard: $this->metadataSchemaGuard(),
      archivalGuard: $this->createStub(FacilityArchivalGuardPort::class),
      interventions: $this->createStub(InterventionScopePort::class),
      eventDispatcher: $dispatcher,
      transactionManager: $transactionManager,
    );

    $handler(new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3, hasName: true, name: 'Renamed'));
  }

  /**
   * Method testAnUnknownFacilityIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownFacilityIsNotFound(): void
  {
    $facilities = $this->createStub(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn(null);

    $this->expectException(FacilityNotFoundException::class);

    $this->handler($facilities)(new PatchCanonicalFacilityCommand(self::FACILITY_ID, 3));
  }

  /**
   * Method testAMalformedIdentifierIsNotFoundRatherThanInvalid.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMalformedIdentifierIsNotFoundRatherThanInvalid(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->expects(self::never())->method('findById');

    $this->expectException(FacilityNotFoundException::class);

    $this->handler($facilities)(new PatchCanonicalFacilityCommand('not-a-uuid', 3));
  }

  /**
   * Method testAStaleRevisionIsRefusedBeforeAnythingIsWritten.
   *
   * @return void no return value
   */
  #[Test]
  public function testAStaleRevisionIsRefusedBeforeAnythingIsWritten(): void
  {
    $facilities = $this->createMock(CanonicalFacilityRepositoryPort::class);
    $facilities->method('findById')->willReturn($this->facility());
    $facilities->expects(self::never())->method('save');

    $this->expectException(FacilityRevisionMismatchException::class);

    $this->handler($facilities)(new PatchCanonicalFacilityCommand(self::FACILITY_ID, 1, hasName: true, name: 'X'));
  }
  // #endregion

  // #region Helpers
  /**
   * Method handler.
   *
   * @param ?CanonicalFacilityRepositoryPort $facilities the canonical repository
   * @param ?FacilityRepositoryPort $facilityRepository the aggregate repository
   * @param ?FacilityArchivalGuardPort $archivalGuard the archival guard
   * @param ?InterventionScopePort $interventions the intervention scope port
   * @param ?EventDispatcherPort $eventDispatcher the event dispatcher
   *
   * @return PatchCanonicalFacilityHandler the handler under test
   */
  private function handler(
    ?CanonicalFacilityRepositoryPort $facilities = null,
    ?FacilityRepositoryPort $facilityRepository = null,
    ?FacilityArchivalGuardPort $archivalGuard = null,
    ?InterventionScopePort $interventions = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): PatchCanonicalFacilityHandler {
    $transactionManager = $this->createStub(TransactionManagerPort::class);
    $transactionManager->method('transactional')->willReturnCallback(
      static fn (callable $operation): mixed => $operation(),
    );

    return new PatchCanonicalFacilityHandler(
      facilities: $facilities ?? $this->createStub(CanonicalFacilityRepositoryPort::class),
      facilityRepository: $facilityRepository ?? $this->createStub(FacilityRepositoryPort::class),
      metadataSchemaGuard: $this->metadataSchemaGuard(),
      archivalGuard: $archivalGuard ?? $this->createStub(FacilityArchivalGuardPort::class),
      interventions: $interventions ?? $this->createStub(InterventionScopePort::class),
      eventDispatcher: $eventDispatcher ?? $this->createStub(EventDispatcherPort::class),
      transactionManager: $transactionManager,
    );
  }

  /**
   * Method metadataSchemaGuard.
   *
   * `FacilityMetadataSchemaGuard` is `final readonly`, so it is built for
   * real over an empty field repository — with no definitions it short-
   * circuits, which is what every test here wants.
   *
   * @return FacilityMetadataSchemaGuard the guard
   */
  private function metadataSchemaGuard(): FacilityMetadataSchemaGuard
  {
    $repository = $this->createStub(FacilityMetadataFieldRepositoryPort::class);
    $repository->method('findByOrganizationId')->willReturn([]);

    return new FacilityMetadataSchemaGuard($repository);
  }

  /**
   * Method facility.
   *
   * @param FacilityRecordStatus $recordStatus the record status
   * @param FacilityStatus $status the facility status
   * @param ?string $parentFacilityId the parent facility
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalFacility a published, active site at revision 3
   */
  private function facility(
    FacilityRecordStatus $recordStatus = FacilityRecordStatus::PUBLISHED,
    FacilityStatus $status = FacilityStatus::ACTIVE,
    ?string $parentFacilityId = null,
    ?string $interventionId = null,
    ?int $levelIndex = null,
  ): CanonicalFacility {
    return CanonicalFacility::reconstitute(
      id: FacilityId::fromString(self::FACILITY_ID),
      organizationId: FacilityOrganizationId::fromString(self::ORGANIZATION_ID),
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      parentFacilityId: $parentFacilityId,
      type: FacilityType::SITE,
      name: 'Main site',
      code: null,
      address: null,
      latitude: null,
      longitude: null,
      metadata: [],
      status: $status,
      revision: 3,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
      levelIndex: $levelIndex,
    );
  }

  /**
   * Method parent.
   *
   * @param FacilityStatus $status the parent status
   * @param string $organizationId the parent's organization
   *
   * @return CanonicalFacility the proposed parent
   */
  private function parent(
    FacilityStatus $status = FacilityStatus::ACTIVE,
    string $organizationId = self::ORGANIZATION_ID,
  ): CanonicalFacility {
    return CanonicalFacility::reconstitute(
      id: FacilityId::fromString(self::PARENT_ID),
      organizationId: FacilityOrganizationId::fromString($organizationId),
      recordStatus: FacilityRecordStatus::PUBLISHED,
      interventionId: null,
      parentFacilityId: null,
      type: FacilityType::SITE,
      name: 'Parent site',
      code: null,
      address: null,
      latitude: null,
      longitude: null,
      metadata: [],
      status: $status,
      revision: 1,
      updatedAt: new DateTimeImmutable('2026-08-26T10:00:00+00:00'),
    );
  }
  // #endregion
}
