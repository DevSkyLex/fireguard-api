<?php

declare(strict_types=1);

namespace Tests\Unit\Equipment\Presentation\Api\Processor\Equipment;

use ApiPlatform\Metadata\{Delete, Patch};
use Auth\Infrastructure\Security\User\SecurityUser;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Equipment\Application\Contract\Equipment\CanonicalEquipmentView;
use Equipment\Application\UseCase\Command\Equipment\DeleteCanonicalEquipment\DeleteCanonicalEquipmentCommand;
use Equipment\Application\UseCase\Command\Equipment\PatchCanonicalEquipment\PatchCanonicalEquipmentCommand;
use Equipment\Application\UseCase\Query\Equipment\GetCanonicalEquipment\GetCanonicalEquipmentResult;
use Equipment\Infrastructure\Persistence\Doctrine\Record\EquipmentRecord;
use Equipment\Presentation\Api\Dto\Input\Equipment\PatchCanonicalEquipmentInput;
use Equipment\Presentation\Api\Dto\Output\Equipment\EquipmentOutput;
use Equipment\Presentation\Api\Processor\Equipment\CanonicalEquipmentMutationProcessor;
use Equipment\Presentation\Api\Provider\Equipment\CanonicalEquipmentProvider;
use Intervention\Application\Contract\Resource\InterventionAssignmentContext;
use Intervention\Application\Port\Outbound\InterventionResourceGatewayPort;
use Intervention\Application\Service\InterventionResourceManager;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Infrastructure\Persistence\Doctrine\Record\OrganizationRecord;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\{CommandMessage, ResultMessage};
use Shared\Application\Port\Inbound\{CommandBusPort, QueryBusPort};
use Shared\Presentation\Api\Http\{MergePatchFields, RevisionGuard};
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, ConflictHttpException, NotFoundHttpException};

/**
 * Test CanonicalEquipmentMutationProcessorTest.
 *
 * The processor no longer persists, decides a lifecycle state, syncs the
 * maintenance log or dispatches an audit event — those moved to
 * `Application\UseCase\{Command,Query}\Equipment\*CanonicalEquipment*` and
 * are covered by their own tests. What is pinned here is what it still owns:
 * the order of its three gates, the permission it picks, the merge-patch
 * `has*` flags, and the facility IRI parse.
 *
 * @category Unit Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(CanonicalEquipmentMutationProcessor::class)]
final class CanonicalEquipmentMutationProcessorTest extends TestCase
{
  // #region Constants
  private const string EQUIPMENT_ID = '550e8400-e29b-41d4-a716-446655440031';

  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655440032';

  private const string USER_ID = '550e8400-e29b-41d4-a716-446655440033';

  private const string INTERVENTION_ID = '550e8400-e29b-41d4-a716-446655440034';

  private const string FACILITY_ID = '550e8400-e29b-41d4-a716-446655440035';
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
    $this->expectExceptionMessage('Equipment not found.');

    $this->processor($this->request('DELETE'), found: false)
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => 42]);
  }

  /**
   * Method testAnUnknownEquipmentIsNotFoundBeforeTheRevisionGuard.
   *
   * The request below carries no `If-Match`. A 428 here would mean the
   * revision guard ran before the row was looked up.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnUnknownEquipmentIsNotFoundBeforeTheRevisionGuard(): void
  {
    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->processor($this->request('DELETE', ifMatch: null), found: false)
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
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
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
  }

  /**
   * Method testAForeignOrganizationIsNotFoundRatherThanForbidden.
   *
   * @return void no return value
   */
  #[Test]
  public function testAForeignOrganizationIsNotFoundRatherThanForbidden(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    $this->expectException(NotFoundHttpException::class);
    $this->expectExceptionMessage('Equipment not found.');

    $this->processor($this->request('DELETE'), authorization: $authorization)
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
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
    $this->expectExceptionMessage('Missing organization.equipment.write permission.');

    $this->processor($this->request('DELETE'), authorization: $authorization)
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
  }

  /**
   * Method testAScratchpadRowAsksTheInterventionForThePermission.
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
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
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
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
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
    )->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);
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
    $this->expectExceptionMessage('Canonical equipment mutation input expected.');

    $this->processor($this->request('PATCH', '{"status":"operational"}'))
      ->process(null, new Patch(), ['id' => self::EQUIPMENT_ID]);
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
      ->process(new PatchCanonicalEquipmentInput(), new Delete(), ['id' => self::EQUIPMENT_ID]);

    self::assertNull($result);
    self::assertCount(1, $commandBus->dispatched);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(DeleteCanonicalEquipmentCommand::class, $command);
    self::assertSame(self::EQUIPMENT_ID, $command->equipmentId);
    self::assertSame(3, $command->expectedRevision);
  }

  /**
   * Method testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand.
   *
   * The whole reason `MergePatchFields` stays in Presentation: `{"brand":null}`
   * must erase the brand, while omitting the key must leave it alone — and
   * the deserialized DTO carries null in both cases.
   *
   * @return void no return value
   */
  #[Test]
  public function testAnAbsentKeyAndAnExplicitNullAreNotTheSameCommand(): void
  {
    $commandBus = $this->recordingCommandBus();

    $this->processor($this->request('PATCH', '{"brand":null}'), commandBus: $commandBus)
      ->process(new PatchCanonicalEquipmentInput(), new Patch(), ['id' => self::EQUIPMENT_ID]);

    $erasing = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalEquipmentCommand::class, $erasing);
    self::assertTrue($erasing->hasBrand);
    self::assertNull($erasing->brand);
    self::assertFalse($erasing->hasModel);
    self::assertFalse($erasing->hasType);
    self::assertFalse($erasing->hasStatus);
    self::assertFalse($erasing->hasFacility);
  }

  /**
   * Method testTheFacilityIriIsParsedIntoAnIdentifier.
   *
   * An IRI is transport; the command carries an identifier.
   *
   * @return void no return value
   */
  #[Test]
  public function testTheFacilityIriIsParsedIntoAnIdentifier(): void
  {
    $commandBus = $this->recordingCommandBus();
    $input = new PatchCanonicalEquipmentInput();
    $input->facility = '/api/facilities/' . self::FACILITY_ID;

    $this->processor(
      $this->request('PATCH', '{"facility":"/api/facilities/' . self::FACILITY_ID . '"}'),
      commandBus: $commandBus,
    )->process($input, new Patch(), ['id' => self::EQUIPMENT_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalEquipmentCommand::class, $command);
    self::assertTrue($command->hasFacility);
    self::assertSame(self::FACILITY_ID, $command->facilityId);
  }

  /**
   * Method testDetachingTheFacilityCarriesAPresentNullIdentifier.
   *
   * @return void no return value
   */
  #[Test]
  public function testDetachingTheFacilityCarriesAPresentNullIdentifier(): void
  {
    $commandBus = $this->recordingCommandBus();

    $this->processor($this->request('PATCH', '{"facility":null}'), commandBus: $commandBus)
      ->process(new PatchCanonicalEquipmentInput(), new Patch(), ['id' => self::EQUIPMENT_ID]);

    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalEquipmentCommand::class, $command);
    self::assertTrue($command->hasFacility);
    self::assertNull($command->facilityId);
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
    $input = new PatchCanonicalEquipmentInput();
    $input->status = 'operational';
    $input->type = 'sprinkler';
    $input->locationLabel = 'Level 2';

    $output = $this->processor(
      $this->request('PATCH', '{"status":"operational","type":"sprinkler","locationLabel":"Level 2"}'),
      commandBus: $commandBus,
    )->process($input, new Patch(), ['id' => self::EQUIPMENT_ID]);

    self::assertInstanceOf(EquipmentOutput::class, $output);
    $command = $commandBus->dispatched[0];
    self::assertInstanceOf(PatchCanonicalEquipmentCommand::class, $command);
    self::assertSame(self::EQUIPMENT_ID, $command->equipmentId);
    self::assertSame(3, $command->expectedRevision);
    self::assertTrue($command->hasStatus);
    self::assertSame('operational', $command->status);
    self::assertTrue($command->hasType);
    self::assertSame('sprinkler', $command->type);
    self::assertTrue($command->hasLocationLabel);
    self::assertSame('Level 2', $command->locationLabel);
  }
  // #endregion

  // #region Helpers
  /**
   * Method processor.
   *
   * @param RequestStack $requestStack the request stack
   * @param ?CanonicalEquipmentView $view the row the query bus answers with
   * @param ?CommandBusPort $commandBus the command bus
   * @param ?OrganizationAuthorizationPort $authorization the authorization port
   * @param ?Security $security the security helper
   * @param ?InterventionResourceGatewayPort $resources the intervention gateway
   * @param bool $found whether the query bus finds the row
   *
   * @return CanonicalEquipmentMutationProcessor the processor under test
   */
  private function processor(
    RequestStack $requestStack,
    ?CanonicalEquipmentView $view = null,
    ?CommandBusPort $commandBus = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?Security $security = null,
    ?InterventionResourceGatewayPort $resources = null,
    bool $found = true,
  ): CanonicalEquipmentMutationProcessor {
    $queryBus = $this->createStub(QueryBusPort::class);
    $queryBus->method('ask')->willReturn(new GetCanonicalEquipmentResult(
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

    return new CanonicalEquipmentMutationProcessor(
      $commandBus ?? $this->createStub(CommandBusPort::class),
      $queryBus,
      $authorization,
      $security,
      $requestStack,
      new CanonicalEquipmentProvider($this->entityManager(), $authorization, $security, $requestStack, $manager),
      $manager,
      new RevisionGuard($requestStack),
      new MergePatchFields($requestStack),
    );
  }

  /**
   * Method entityManager.
   *
   * Only `CanonicalEquipmentProvider` reaches it — the processor holds none.
   *
   * @return EntityManagerInterface a manager answering with one seeded record
   */
  private function entityManager(): EntityManagerInterface
  {
    $organization = new OrganizationRecord();
    $organization->id = self::ORGANIZATION_ID;
    $record = new EquipmentRecord();
    $record->id = self::EQUIPMENT_ID;
    $record->organization = $organization;
    $record->recordStatus = 'published';
    $record->revision = 3;
    $record->type = 'fire_extinguisher';
    $record->status = 'in_stock';
    $record->createdAt = new DateTimeImmutable();
    $record->updatedAt = new DateTimeImmutable();
    $record->attachments = new ArrayCollection();
    $record->tagLinks = new ArrayCollection();

    $entityManager = $this->createStub(EntityManagerInterface::class);
    $entityManager->method('find')->willReturn($record);

    return $entityManager;
  }

  /**
   * Method view.
   *
   * @param string $recordStatus the record status
   * @param ?string $interventionId the preparing intervention
   *
   * @return CanonicalEquipmentView the gate projection
   */
  private function view(string $recordStatus = 'published', ?string $interventionId = null): CanonicalEquipmentView
  {
    return new CanonicalEquipmentView(
      id: self::EQUIPMENT_ID,
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
    $request = Request::create('/api/equipment/' . self::EQUIPMENT_ID, $method, [], [], [], [], $content);
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
