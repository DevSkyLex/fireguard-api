<?php

declare(strict_types=1);

namespace Tests\Unit\Facility\Presentation\Api\Processor\Facility;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Facility\Application\Contract\Facility\CanonicalFacilityView;
use Facility\Application\UseCase\Command\Facility\DeleteCanonicalFacility\DeleteCanonicalFacilityCommand;
use Facility\Application\UseCase\Command\Facility\PatchCanonicalFacility\PatchCanonicalFacilityCommand;
use Facility\Application\UseCase\Query\Facility\GetCanonicalFacility\{GetCanonicalFacilityQuery, GetCanonicalFacilityResult};
use Facility\Domain\Exception\{FacilityAccessDeniedException, FacilityNotFoundException};
use Facility\Presentation\Api\Dto\Input\Facility\PatchCanonicalFacilityInput;
use Facility\Presentation\Api\Dto\Output\Facility\FacilityOutput;
use Facility\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Facility\Presentation\Api\Processor\Facility\CanonicalFacilityMutationProcessor;
use Facility\Presentation\Api\Provider\Facility\CanonicalFacilityProvider;
use Intervention\Application\Contract\Resource\{InterventionConflictException, InterventionNotFoundException};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, NotFoundHttpException};
use Throwable;

/**
 * Test CanonicalFacilityMutationProcessorTest.
 *
 * The processor no longer persists, walks the hierarchy, decides a lifecycle
 * state or dispatches an audit event — those moved to
 * `Application\UseCase\{Command,Query}\Facility\*CanonicalFacility*` and are
 * covered by their own tests. What is pinned here is what it still owns: the
 * order of its three gates, the permission it picks, the merge-patch `has*`
 * flags, and the parent IRI parse.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalFacilityMutationProcessor::class)]
final class CanonicalFacilityMutationProcessorTest extends TestCase
{
  // #region Constants
  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440041';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440042';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440043';

  private const string PARENT_ID = '550e8400-e29b-41d4-a716-446655440045';
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
    $this->expectExceptionMessage('Facility not found.');

    $this->processor($this->request('DELETE'), found: false)
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => 42]);
  }

  /**
   * Method testAnUnknownFacilityIsNotFoundBeforeTheRevisionGuard.
   *
   * The request below carries no `If-Match`. A 428 here would mean the
   * revision guard ran before the row was looked up.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownFacilityIsNotFoundBeforeTheRevisionGuard(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Facility not found.');

    $this->processor($this->request('DELETE', ifMatch: null), found: false)
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
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
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  /**
   * Method testAForeignOrganizationIsNotFoundRatherThanForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignOrganizationIsNotFoundRatherThanForbidden(): void
  {
    $this->expectException(FacilityNotFoundException::class);
    $this->expectExceptionMessage('Facility not found.');
    $this->processor($this->request('DELETE', ifMatch: null), queryFailure: new FacilityNotFoundException('Facility not found.'))
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  /**
   * Method testAMissingPermissionIsForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMissingPermissionIsForbidden(): void
  {
    $this->expectException(FacilityAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.facilities.write permission.');
    $this->processor($this->request('DELETE', ifMatch: null), queryFailure: new FacilityAccessDeniedException('Missing organization.facilities.write permission.'))
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  /**
   * Method testAScratchpadRowAsksTheInterventionForThePermission.
   *
   * @return void no return value
   */
  #[Test]
  public function testAScratchpadRowAsksTheInterventionForThePermission(): void
  {
    $this->expectException(FacilityAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.interventions.plan permission.');
    $this->processor($this->request('DELETE', ifMatch: null), queryFailure: new FacilityAccessDeniedException('Missing organization.interventions.plan permission.'))
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  /**
   * Method testAMissingParentInterventionIsNotFound.
   *
   * @return void no return value
   */
  #[Test]
  public function testAMissingParentInterventionIsNotFound(): void
  {
    $this->expectException(InterventionNotFoundException::class);
    $this->expectExceptionMessage('Intervention not found.');
    $this->processor($this->request('DELETE', ifMatch: null), queryFailure: new InterventionNotFoundException('Intervention not found.'))
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
  }

  /**
   * Method testAnImmutableParentInterventionIsAConflict.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnImmutableParentInterventionIsAConflict(): void
  {
    $this->expectException(InterventionConflictException::class);
    $this->expectExceptionMessage('Intervention is immutable.');
    $this->processor($this->request('DELETE', ifMatch: null), queryFailure: new InterventionConflictException('Intervention is immutable.'))
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);
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
    $this->expectExceptionMessage('Canonical facility mutation input expected.');

    $this->processor($this->request('PATCH', '{"name":"Nope"}'))
      ->process(null, new Patch(), ['id' => self::FACILITY_ID]);
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
      ->process(new PatchCanonicalFacilityInput(), new Delete(), ['id' => self::FACILITY_ID]);

    self::assertNull($result);
    self::assertCount(1, $commandBus->dispatched);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(DeleteCanonicalFacilityCommand::class, $command);
    self::assertSame(self::FACILITY_ID, $command->facilityId);
    self::assertSame(3, $command->expectedRevision);
    self::assertSame(self::USER_ID, $command->userId);
  }

  /**
   * Method testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand.
   *
   * The whole reason `MergePatchFields` stays in Presentation: `{"code":null}`
   * must erase the code, while omitting the key must leave it alone — and the
   * deserialized DTO carries null in both cases.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand(): void
  {
    $commandBus = $this->recordingCommandBus();

    $this->processor($this->request('PATCH', '{"code":null}'), commandBus: $commandBus)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasCode);
    self::assertNull($command->code);
    self::assertFalse($command->hasAddress);
    self::assertFalse($command->hasName);
    self::assertFalse($command->hasStatus);
    self::assertFalse($command->hasParent);
  }

  /**
   * Method testTheParentIriIsParsedIntoAnIdentifier.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheParentIriIsParsedIntoAnIdentifier(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalFacilityInput();
    $input->parent = '/api/facilities/' . self::PARENT_ID;

    $this->processor(
      $this->request('PATCH', '{"parent":"/api/facilities/' . self::PARENT_ID . '"}'),
      commandBus: $commandBus,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasParent);
    self::assertSame(self::PARENT_ID, $command->parentFacilityId);
  }

  /**
   * Method testDetachingTheParentCarriesAPresentNullIdentifier.
   *
   * @return void no return value
   */
  #[Test]
  public function testDetachingTheParentCarriesAPresentNullIdentifier(): void
  {
    $commandBus = $this->recordingCommandBus();

    $this->processor($this->request('PATCH', '{"parent":null}'), commandBus: $commandBus)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasParent);
    self::assertNull($command->parentFacilityId);
  }

  /**
   * Method testASingleCoordinateStillTravelsSoTheHandlerCanRejectIt.
   *
   * The pairing rule is the domain's, not the processor's: sending only
   * `latitude` must arrive as `hasLatitude` true and `hasLongitude` false, or
   * the handler has nothing to reject.
   *
   * @return void no return value
   */
  #[Test]
  public function testASingleCoordinateStillTravelsSoTheHandlerCanRejectIt(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalFacilityInput();
    $input->latitude = 48.85;

    $this->processor($this->request('PATCH', '{"latitude":48.85}'), commandBus: $commandBus)
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasLatitude);
    self::assertSame(48.85, $command->latitude);
    self::assertFalse($command->hasLongitude);
  }

  /**
   * Method testAnAbsentLevelIndexKeyAndAnExplicitNullAreNotTheSameCommand.
   *
   * Mirrors {@see self::testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand}
   * for `levelIndex`: this processor's sentinel is `PATCHABLE_FIELDS` plus
   * `MergePatchFields`, a distinct mechanism from `UpdateFacilityProcessor`'s
   * `array_key_exists`.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentLevelIndexKeyAndAnExplicitNullAreNotTheSameCommand(): void
  {
    $commandBus = $this->recordingCommandBus();

    $this->processor($this->request('PATCH', '{"levelIndex":null}'), commandBus: $commandBus)
      ->process(new PatchCanonicalFacilityInput(), new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasLevelIndex);
    self::assertNull($command->levelIndex);
    self::assertFalse($command->hasName);
    self::assertFalse($command->hasCode);
  }

  /**
   * Method testAnAbsentLevelIndexKeyIsNotForwarded.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentLevelIndexKeyIsNotForwarded(): void
  {
    $commandBus = $this->recordingCommandBus();

    $input = new PatchCanonicalFacilityInput();
    $input->name = 'Renamed site';

    $this->processor($this->request('PATCH', '{"name":"Renamed site"}'), commandBus: $commandBus)
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertFalse($command->hasLevelIndex);
  }

  /**
   * Method testPatchForwardsTheSubmittedLevelIndex.
   *
   * @return void no return value
   */
  #[Test]
  public function testPatchForwardsTheSubmittedLevelIndex(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalFacilityInput();
    $input->levelIndex = -1;

    $this->processor($this->request('PATCH', '{"levelIndex":-1}'), commandBus: $commandBus)
      ->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertTrue($command->hasLevelIndex);
    self::assertSame(-1, $command->levelIndex);
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
    $input = new PatchCanonicalFacilityInput();
    $input->name = 'Renamed site';
    $input->status = 'archived';
    $input->metadata = ['floorCount' => 3];

    $output = $this->processor(
      $this->request('PATCH', '{"name":"Renamed site","status":"archived","metadata":{"floorCount":3}}'),
      commandBus: $commandBus,
    )->process($input, new Patch(), ['id' => self::FACILITY_ID]);

    self::assertInstanceOf(FacilityOutput::class, $output);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalFacilityCommand::class, $command);
    self::assertSame(self::FACILITY_ID, $command->facilityId);
    self::assertSame(3, $command->expectedRevision);
    self::assertSame(self::USER_ID, $command->userId);
    self::assertTrue($command->hasName);
    self::assertSame('Renamed site', $command->name);
    self::assertTrue($command->hasStatus);
    self::assertSame('archived', $command->status);
    self::assertTrue($command->hasMetadata);
    self::assertSame(['floorCount' => 3], $command->metadata);
  }
  // #endregion

  // #region Helpers
  /**
   * Method processor.
   *
   * @param RequestStack $requestStack the request stack
   * @param ?CanonicalFacilityView $view the row the query bus answers with
   * @param ?CommandBusPort $commandBus the command bus
   * @param ?Security $security the security helper
   * @param ?Throwable $queryFailure the application refusal returned before the revision gate
   * @param bool $found whether the query bus finds the row
   *
   * @return CanonicalFacilityMutationProcessor the processor under test
   */
  private function processor(
    RequestStack $requestStack,
    ?CanonicalFacilityView $view = null,
    ?CommandBusPort $commandBus = null,
    ?Security $security = null,
    ?Throwable $queryFailure = null,
    bool $found = true,
  ): CanonicalFacilityMutationProcessor {
    $queryBus = $this->createMock(QueryBusPort::class);
    $expectedActor = null === $security ? self::USER_ID : null;
    $queryExpectation = $queryBus->expects(self::once())->method('ask')->with(self::callback(static fn (object $query): bool => $query instanceof GetCanonicalFacilityQuery && $query->userId === $expectedActor && (self::FACILITY_ID === $query->facilityId || (!$found && '' === $query->facilityId))));
    if (null !== $queryFailure) {
      $queryExpectation->willThrowException($queryFailure);
      $commandBus = $this->createMock(CommandBusPort::class);
      $commandBus->expects(self::never())->method('dispatch');
    } else {
      $queryExpectation->willReturn(new GetCanonicalFacilityResult($found ? $view ?? $this->view() : null));
    }

    if (null === $security) {
      $security = $this->createStub(Security::class);
      $security->method('getUser')->willReturn(
        new SecurityUser(self::USER_ID, 'user@example.com', 'password', ['ROLE_USER'], [], true),
      );
    }

    $provider = new CanonicalFacilityProvider(
      $security,
      $requestStack,
      new \Facility\Presentation\Api\Factory\FacilityDetailOutputFactory(),
      $this->detailQueries(),
    );

    return new CanonicalFacilityMutationProcessor(
      $commandBus ?? $this->createStub(CommandBusPort::class),
      $queryBus,
      $security,
      $requestStack,
      $provider,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
    );
  }

  /**
   * Method view.
   *
   * @param string $recordStatus the record status
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalFacilityView the gate projection
   */
  private function view(string $recordStatus = 'published', ?string $interventionId = null): CanonicalFacilityView
  {
    return new CanonicalFacilityView(
      id: self::FACILITY_ID,
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
    $request = Request::create('/api/facilities/' . self::FACILITY_ID, $method, [], [], [], [], $content);
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

  private function detailQueries(): QueryBusPort
  {
    $queries = $this->createStub(QueryBusPort::class);
    $queries->method('ask')->willReturnCallback(static fn (\Shared\Application\Message\QueryMessage $query): ResultMessage => $query instanceof \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityQuery
      ? new \Facility\Application\UseCase\Query\Facility\GetFacility\GetFacilityResult(
        facilityId: self::FACILITY_ID,
        organizationId: self::ORGANIZATION_ID,
        parentFacilityId: null,
        type: 'building',
        name: 'Building B',
        code: null,
        status: 'active',
        address: null,
        metadata: [],
        path: [['id' => 'root-id', 'name' => 'Root', 'type' => 'site']],
        createdAt: new DateTimeImmutable('2026-09-20'),
        updatedAt: new DateTimeImmutable('2026-09-20'),
      )
      : new \Facility\Application\UseCase\Query\Attachment\GetFacilityMediaContext\GetFacilityMediaContextResult(self::ORGANIZATION_ID, self::FACILITY_ID));

    return $queries;
  }
}
