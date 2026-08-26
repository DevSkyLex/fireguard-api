<?php

declare(strict_types=1);

namespace Tests\Unit\Inspection\Presentation\Api\Processor\Inspection;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Inspection\Application\Contract\Inspection\{CanonicalInspectionReadView, CanonicalInspectionView};
use Inspection\Application\UseCase\Command\Inspection\DeleteCanonicalInspection\DeleteCanonicalInspectionCommand;
use Inspection\Application\UseCase\Command\Inspection\PatchCanonicalInspection\PatchCanonicalInspectionCommand;
use Inspection\Application\UseCase\Query\Inspection\GetCanonicalInspection\GetCanonicalInspectionResult;
use Inspection\Application\UseCase\Query\Inspection\ReadCanonicalInspection\ReadCanonicalInspectionResult;
use Inspection\Presentation\Api\Dto\Input\Inspection\PatchCanonicalInspectionInput;
use Inspection\Presentation\Api\Dto\Output\Inspection\InspectionOutput;
use Inspection\Presentation\Api\Processor\Inspection\CanonicalInspectionMutationProcessor;
use Inspection\Presentation\Api\Provider\Inspection\CanonicalInspectionProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

/**
 * Test CanonicalInspectionMutationProcessorTest.
 *
 * The processor no longer persists, decides a lifecycle state or dispatches
 * an audit event — those moved to
 * `Application\UseCase\{Command,Query}\Inspection\*CanonicalInspection*` and
 * are covered by their own tests. What is pinned here is what it still owns:
 * the order of its three gates, the permission it picks, and the merge-patch
 * `has*` flags — the one fact about the HTTP body a deserialized DTO cannot
 * carry.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalInspectionMutationProcessor::class)]
final class CanonicalInspectionMutationProcessorTest extends TestCase
{
  // #region Constants
  private const string INSPECTION_ID = '550e8400-e29b-41d4-a716-446655440021';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440022';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440023';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440024';
  // #endregion

  // #region Tests — the gates
  /**
   * Method testANonStringIdentifierIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testANonStringIdentifierIsNotFound(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Inspection not found.');

    $this->processor($this->request('DELETE'), found: false)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => 42]);
  }

  /**
   * Method testAnUnknownInspectionIsNotFoundBeforeTheRevisionGuard.
   *
   * The request below carries no `If-Match`. A 428 here would mean the
   * revision guard ran before the row was looked up — the ordering this
   * surface has always had, and the natural one to break.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownInspectionIsNotFoundBeforeTheRevisionGuard(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Inspection not found.');

    $this->processor($this->request('DELETE', ifMatch: null), found: false)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAnUnauthenticatedCallerIsRefused.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnauthenticatedCallerIsRefused(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Authentication required.');

    $this->processor($this->request('DELETE'), security: $security)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAForeignOrganizationIsNotFoundRatherThanForbidden.
   *
   * The row is loaded by GLOBAL id, so a 403 would confirm that id exists in
   * another tenant.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignOrganizationIsNotFoundRatherThanForbidden(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Inspection not found.');

    $this->processor($this->request('DELETE'), authorization: $authorization)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAMissingPermissionIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMissingPermissionIsForbidden(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.inspection.write permission.');

    $this->processor($this->request('DELETE'), authorization: $authorization)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAScratchpadRowAsksTheInterventionForThePermission.
   *
   * A draft record inside an intervention is gated on whatever
   * `mutationPermission()` resolves, not on `organization.inspection.write`.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadRowAsksTheInterventionForThePermission(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft'),
    );
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(AccessDeniedHttpException::class);
    $this->expectExceptionMessage('Missing organization.interventions.plan permission.');

    $this->processor(
      $this->request('DELETE'),
      view: $this->view(recordStatus: 'draft', interventionId: self::INTERVENTION_ID),
      authorization: $authorization,
      resources: $resources,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAMissingParentInterventionIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMissingParentInterventionIsNotFound(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(null);

    $this->expectException(NotFoundHttpException::class);

    $this->processor(
      $this->request('DELETE'),
      view: $this->view(recordStatus: 'draft', interventionId: self::INTERVENTION_ID),
      resources: $resources,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAnImmutableParentInterventionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnImmutableParentInterventionIsAConflict(): void
  {
    $resources = $this->createStub(InterventionResourceGatewayPort::class);
    $resources->method('interventionMutationContext')->willReturn(
      new InterventionAssignmentContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'published'),
    );

    $this->expectException(ConflictHttpException::class);

    $this->processor(
      $this->request('DELETE'),
      view: $this->view(recordStatus: 'draft', interventionId: self::INTERVENTION_ID),
      resources: $resources,
    )->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);
  }

  /**
   * Method testAPatchWithoutTheCanonicalInputIsABadRequest.
   *
   * @return void no return value
   */
  #[Test]
  public function testAPatchWithoutTheCanonicalInputIsABadRequest(): void
  {
    $this->expectException(BadRequestHttpException::class);
    $this->expectExceptionMessage('Canonical inspection mutation input expected.');

    $this->processor($this->request('PATCH', '{"status":"closed"}'))
      ->process(null, new Patch(), ['id' => self::INSPECTION_ID]);
  }
  // #endregion

  // #region Tests — what travels into the commands
  /**
   * Method testDeleteCarriesTheStoredRevisionAndReturnsNothing.
   *
   * @return void no return value
   */
  #[Test]
  public function testDeleteCarriesTheStoredRevisionAndReturnsNothing(): void
  {
    $commandBus = $this->recordingCommandBus();

    $result = $this->processor($this->request('DELETE'), commandBus: $commandBus)
      ->process(new PatchCanonicalInspectionInput(), new Delete(), ['id' => self::INSPECTION_ID]);

    self::assertNull($result);
    self::assertCount(1, $commandBus->dispatched);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(DeleteCanonicalInspectionCommand::class, $command);
    self::assertSame(self::INSPECTION_ID, $command->inspectionId);
    self::assertSame(3, $command->expectedRevision);
  }

  /**
   * Method testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand.
   *
   * The whole reason `MergePatchFields` stays in Presentation: `{"notes":null}`
   * must erase the note, while omitting the key must leave it alone — and the
   * deserialized DTO carries null in both cases.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalInspectionInput();
    $input->notes = null;

    $this->processor($this->request('PATCH', '{"notes":null}'), commandBus: $commandBus)
      ->process($input, new Patch(), ['id' => self::INSPECTION_ID]);

    $erasing = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalInspectionCommand::class, $erasing);
    self::assertTrue($erasing->hasNotes);
    self::assertNull($erasing->notes);
    self::assertFalse($erasing->hasSignature);
    self::assertFalse($erasing->hasResult);
    self::assertFalse($erasing->hasStatus);

    $untouched = $this->recordingCommandBus();
    $this->processor($this->request('PATCH', '{"signature":"ok"}'), commandBus: $untouched)
      ->process(new PatchCanonicalInspectionInput(), new Patch(), ['id' => self::INSPECTION_ID]);

    $command = $untouched->dispatched[0];
    self::assertInstanceOf(PatchCanonicalInspectionCommand::class, $command);
    self::assertFalse($command->hasNotes);
    self::assertTrue($command->hasSignature);
  }

  /**
   * Method testPatchForwardsTheSubmittedValuesAndTheStoredRevision.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchForwardsTheSubmittedValuesAndTheStoredRevision(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalInspectionInput();
    $input->status = 'closed';
    $input->result = 'fail';

    $output = $this->processor($this->request('PATCH', '{"status":"closed","result":"fail"}'), commandBus: $commandBus)
      ->process($input, new Patch(), ['id' => self::INSPECTION_ID]);

    self::assertInstanceOf(InspectionOutput::class, $output);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalInspectionCommand::class, $command);
    self::assertSame(self::INSPECTION_ID, $command->inspectionId);
    self::assertSame(3, $command->expectedRevision);
    self::assertTrue($command->hasStatus);
    self::assertSame('closed', $command->status);
    self::assertTrue($command->hasResult);
    self::assertSame('fail', $command->result);
  }
  // #endregion

  // #region Helpers
  /**
   * Method processor.
   *
   * @param RequestStack $requestStack the request stack
   * @param ?CanonicalInspectionView $view the row the query bus answers with, null for "not found"
   * @param ?CommandBusPort $commandBus the command bus
   * @param ?OrganizationAuthorizationPort $authorization the authorization port
   * @param ?Security $security the security helper
   * @param ?InterventionResourceGatewayPort $resources the intervention gateway
   *
   * @return CanonicalInspectionMutationProcessor the processor under test
   */
  private function processor(
    RequestStack $requestStack,
    ?CanonicalInspectionView $view = null,
    ?CommandBusPort $commandBus = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?Security $security = null,
    ?InterventionResourceGatewayPort $resources = null,
    bool $found = true,
  ): CanonicalInspectionMutationProcessor {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetCanonicalInspectionResult(
      $found ? $view ?? $this->view() : null,
    ));

    if (null === $authorization) {
      $authorization = $this->createStub(OrganizationAuthorizationPort::class);
      $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);
    }

    if (null === $security) {
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );
    }

    $manager = new InterventionResourceManager(
      $resources ?? $this->createStub(InterventionResourceGatewayPort::class),
    );

    return new CanonicalInspectionMutationProcessor(
      $commandBus ?? $this->createStub(CommandBusPort::class),
      $queryBus,
      $authorization,
      $security,
      $requestStack,
      new CanonicalInspectionProvider($this->readQueryBus(), $authorization, $security, $requestStack),
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
    );
  }

  /**
   * Method readQueryBus.
   *
   * Only `CanonicalInspectionProvider` reaches it — the processor holds no
   * bus of its own for reads. It answers one seeded inspection so the PATCH
   * path can produce its output.
   *
   * @return QueryBusPort a bus answering the canonical read
   */
  private function readQueryBus(): QueryBusPort
  {
    $now = new DateTimeImmutable();
    $view = new CanonicalInspectionReadView(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      interventionId: null,
      recordStatus: 'published',
      revision: 3,
      equipmentId: '550e8400-e29b-41d4-a716-446655440025',
      facilityId: null,
      result: 'pass',
      status: 'submitted',
      performedAt: $now,
      inspectorType: 'external',
      inspectorUserId: null,
      inspectorName: 'Inspector',
      inspectorOrganizationName: null,
      checklistId: null,
      notes: null,
      signature: null,
      createdAt: $now,
      updatedAt: $now,
    );

    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new ReadCanonicalInspectionResult($view));

    return $queryBus;
  }

  /**
   * Method view.
   *
   * @param string $recordStatus the record status
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalInspectionView the gate projection
   */
  private function view(string $recordStatus = 'published', ?string $interventionId = null): CanonicalInspectionView
  {
    return new CanonicalInspectionView(
      id: self::INSPECTION_ID,
      organizationId: self::ORGANIZATION_ID,
      recordStatus: $recordStatus,
      interventionId: $interventionId,
      revision: 3,
    );
  }

  /**
   * Method request.
   *
   * @param string $method the HTTP method
   * @param ?string $content the raw body
   * @param ?string $ifMatch the If-Match header, null to omit it
   *
   * @return RequestStack the stack holding the request
   */
  private function request(string $method, ?string $content = null, ?string $ifMatch = '"revision-3"'): RequestStack
  {
    $request = Request::create('/api/inspections/' . self::INSPECTION_ID, $method, [], [], [], [], $content);
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }
    $stack = new RequestStack();
    $stack->push($request);

    return $stack;
  }

  /**
   * Method recordingCommandBus.
   *
   * @return CommandBusPort&object{dispatched: list<CommandMessage>} a bus that keeps what it was given
   */
  private function recordingCommandBus(): CommandBusPort
  {
    return new class () implements CommandBusPort {
      /**
       * @var list<CommandMessage>
       */
      public array $dispatched = [];

      public function dispatch(CommandMessage $command): ResultMessage
      {
        $this->dispatched[] = $command;

        return new class () implements ResultMessage {
        };
      }
    };
  }
  // #endregion
}
