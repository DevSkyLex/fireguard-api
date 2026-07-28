<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Inspection\Domain\Event\Inspection\{InspectionCancelledEvent, InspectionClosedEvent, InspectionSubmittedEvent};
use Inspection\Infrastructure\Persistence\Doctrine\Record\InspectionRecord;
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Processor\Inspection\CanonicalInspectionMutationProcessor;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException, UnprocessableEntityHttpException};

#[CoversClass(CanonicalInspectionMutationProcessor::class)]
final class CanonicalInspectionMutationProcessorTest extends TestCase
{
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440023';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';

  #[Test]
  public function testDeletingDraftInspectionRemovesIt(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('remove')->with($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createMock(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );
    $resources->expects(self::once())->method('touchDraftIntervention')->with(self::INTERVENTION_ID);

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
      $resources,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testDeletingPublishedInspectionCancelsItInsteadOfClosing(): void
  {
    // A published inspection (submitted) is logically annulled, not force-closed:
    // its status becomes `cancelled` and its non-conformities are left untouched.
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertSame('cancelled', $record->status);
    self::assertSame(4, $record->revision);
  }

  #[Test]
  public function testDeletingAlreadyCancelledInspectionIsIdempotent(): void
  {
    // A repeat DELETE must not bump the revision, matching the facility and
    // equipment canonical surfaces.
    $record = $this->record();
    $record->status = 'cancelled';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('remove');
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertSame('cancelled', $record->status);
    self::assertSame(3, $record->revision);
  }

  #[Test]
  public function testRejectsDeletingClosedPublishedInspection(): void
  {
    // Closed is terminal: it cannot be cancelled, mirroring Inspection::cancel().
    $record = $this->record();
    $record->status = 'closed';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');
    $entityManager->expects(self::never())->method('remove');

    $this->expectException(ConflictHttpException::class);

    $this->processor(
      $record,
      $this->request('DELETE'),
      $entityManager,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsPatchingCancelledInspection(): void
  {
    // Cancelled is terminal on the canonical surface too: no further mutation.
    $record = $this->record();
    $record->status = 'cancelled';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(ConflictHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"submitted"}'),
      $entityManager,
    )->process($this->patchStatus('submitted'), new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsDraftToClosedTransition(): void
  {
    $record = $this->record();
    $record->status = 'draft';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testAllowsSubmittedToClosedTransition(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('closed', $record->status);
    self::assertSame(4, $record->revision);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testDraftRecordSkipsStatusTransitionValidation(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'draft';

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::once())->method('flush');

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    // A draft (intervention scratchpad) record may be freely edited — an
    // otherwise-illegal transition (draft -> closed) must not throw.
    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
      $resources,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('closed', $record->status);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testDeletingPublishedInspectionDispatchesCancelledEvent(): void
  {
    // Audit ledger: cancelling a published inspection through the canonical
    // DELETE emits an InspectionCancelledEvent carrying the pre-cancel status.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionCancelledEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::INSPECTION_ID === $event->inspectionId
        && '550e8400-e29b-41d4-a716-446655440025' === $event->equipmentId
        && 'submitted' === $event->previousStatus,
    ));

    $result = $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertSame('cancelled', $record->status);
  }

  #[Test]
  public function testClosingPublishedInspectionDispatchesClosedEvent(): void
  {
    // Audit ledger: a published submitted -> closed PATCH emits an
    // InspectionClosedEvent carrying the recorded result.
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionClosedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::INSPECTION_ID === $event->inspectionId
        && '550e8400-e29b-41d4-a716-446655440025' === $event->equipmentId
        && 'pass' === $event->result,
    ));

    $result = $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('closed', $record->status);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testRepeatDeleteOnCancelledInspectionDispatchesNothing(): void
  {
    // The idempotent repeat DELETE leaves the record untouched: no ledger row.
    $record = $this->record();
    $record->status = 'cancelled';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testDeletingDraftInspectionDispatchesNothing(): void
  {
    // A draft (intervention scratchpad) hard-delete never reaches the ledger.
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('DELETE'),
      resources: $resources,
      eventDispatcher: $eventDispatcher,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testPatchingDraftInspectionDispatchesNothing(): void
  {
    // Draft scratchpad edits are not audited, even on a status change.
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;
    $record->status = 'draft';

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress'),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"submitted"}'),
      resources: $resources,
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('submitted'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('submitted', $record->status);
  }

  #[Test]
  public function testSubmittingPublishedInspectionDispatchesSubmittedEvent(): void
  {
    // Audit ledger: a published draft -> submitted PATCH emits an
    // InspectionSubmittedEvent carrying the recorded result.
    $record = $this->record();
    $record->status = 'draft';

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionSubmittedEvent
        && self::ORGANIZATION_ID === $event->organizationId
        && self::INSPECTION_ID === $event->inspectionId
        && '550e8400-e29b-41d4-a716-446655440025' === $event->equipmentId
        && 'pass' === $event->result,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"submitted"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('submitted'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('submitted', $record->status);
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
    $entityManager->method('find')->with(InspectionRecord::class, self::INSPECTION_ID)->willReturn($record);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(RuntimeException::class);

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"closed"}'),
      $entityManager,
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('closed'), new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsANonStringIdentifier(): void
  {
    $record = $this->record();

    $this->expectException(NotFoundHttpException::class);

    $this->processor($record, $this->request('DELETE'))
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => 42]);
  }

  #[Test]
  public function testReportsAnUnknownInspectionAsNotFound(): void
  {
    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->processor($this->record(), $this->request('DELETE'), $entityManager)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsAPatchWithoutTheCanonicalInput(): void
  {
    $record = $this->record();

    $this->expectException(BadRequestHttpException::class);

    $this->processor($record, $this->request('PATCH', '{"status":"closed"}'))
      ->process(null, new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsANullStatusInTheMergePatch(): void
  {
    $record = $this->record();

    $entityManager = $this->entityManager($record);
    $entityManager->expects(self::never())->method('flush');

    $this->expectException(UnprocessableEntityHttpException::class);

    $this->processor($record, $this->request('PATCH', '{"status":null}'), $entityManager)
      ->process(new PatchCanonicalInspectionInput(), new Patch(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testAppliesNullableNotesAndSignatureFromTheMergePatch(): void
  {
    $record = $this->record();
    $record->notes = 'Previous notes';
    $record->signature = 'Previous signature';

    $input = new PatchCanonicalInspectionInput();
    $input->notes = 'Updated notes';

    $result = $this->processor($record, $this->request('PATCH', '{"notes":"Updated notes","signature":null}'))
      ->process($input, new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('Updated notes', $record->notes);
    self::assertNull($record->signature);
    self::assertSame(4, $result?->revision);
  }

  #[Test]
  public function testCancellingAPublishedInspectionThroughPatchDispatchesCancelledEvent(): void
  {
    $record = $this->record();

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())->method('dispatch')->with(self::callback(
      static fn (object $event): bool => $event instanceof InspectionCancelledEvent
        && 'submitted' === $event->previousStatus,
    ));

    $this->processor(
      $record,
      $this->request('PATCH', '{"status":"cancelled"}'),
      eventDispatcher: $eventDispatcher,
    )->process($this->patchStatus('cancelled'), new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertSame('cancelled', $record->status);
  }

  #[Test]
  public function testRequiresAnAuthenticatedSecurityUser(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $requestStack = $this->request('DELETE');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $manager = new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class));

    $processor = new CanonicalInspectionMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      new CanonicalInspectionProvider($entityManager, $authorization, $security, $requestStack, $manager),
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testRejectsAMissingPermission(): void
  {
    $record = $this->record();
    $entityManager = $this->entityManager($record);
    $requestStack = $this->request('DELETE');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);
    $manager = new InterventionResourceManager($this->createStub(InterventionResourceGatewayPort::class));

    $processor = new CanonicalInspectionMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      new CanonicalInspectionProvider($entityManager, $authorization, $security, $requestStack, $manager),
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $this->createStub(EventDispatcherPort::class),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testReportsAMissingParentInterventionAsNotFound(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->processor($record, $this->request('DELETE'), resources: $resources)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  #[Test]
  public function testReportsAnImmutableParentInterventionAsConflict(): void
  {
    $record = $this->record();
    $record->recordStatus = 'draft';
    $record->interventionId = self::INTERVENTION_ID;

    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'published'),
    );

    $this->expectException(ConflictHttpException::class);

    $this->processor($record, $this->request('DELETE'), resources: $resources)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  private function processor(
    InspectionRecord $record,
    RequestStack $requestStack,
    ?EntityManagerInterface $entityManager = null,
    ?InterventionResourceGatewayPort $resources = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): CanonicalInspectionMutationProcessor {
    $entityManager ??= $this->entityManager($record);
    $resources ??= $this->createStub(InterventionResourceGatewayPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->user());
    $manager = new InterventionResourceManager($resources);
    $provider = new CanonicalInspectionProvider(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $manager,
    );

    return new CanonicalInspectionMutationProcessor(
      $entityManager,
      $authorization,
      $security,
      $requestStack,
      $provider,
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
      $eventDispatcher,
    );
  }

  /**
   * @return EntityManagerInterface&MockObject
   */
  private function entityManager(InspectionRecord $record): EntityManagerInterface
  {
    $entityManager = $this->createMock(EntityManagerInterface::class);
    $entityManager->method('wrapInTransaction')->willReturnCallback(
      static fn (callable $callback): mixed => $callback(),
    );
    $entityManager->method('find')->with(InspectionRecord::class, self::INSPECTION_ID)->willReturn($record);

    return $entityManager;
  }

  private function record(): InspectionRecord
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new InspectionRecord();
    $record->id = self::INSPECTION_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->status = 'submitted';
    $record->inspectorType = 'external';
    $record->inspectorName = 'Inspector';
    $record->equipmentId = '550e8400-e29b-41d4-a716-446655440025';
    $record->result = 'pass';
    $record->performedAt = new DateTimeImmutable();
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();
    $record->nonConformities = new ArrayCollection();

    return $record;
  }

  private function request(string $method, ?string $content = null): RequestStack
  {
    $request = Request::create('/api/inspections/' . self::INSPECTION_ID, $method, [], [], [], [], $content);
    $request->headers->set('If-Match', '"revision-3"');
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  private function patchStatus(string $status): PatchCanonicalInspectionInput
  {
    $input = new PatchCanonicalInspectionInput();
    $input->status = $status;

    return $input;
  }

  private function user(): SecurityUser
  {
    return new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true);
  }
}
