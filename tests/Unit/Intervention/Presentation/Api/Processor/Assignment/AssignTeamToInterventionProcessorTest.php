<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Presentation\Api\Processor\Assignment;

use ApiPlatform\Metadata\Post;
use Auth\Infrastructure\Security\User\SecurityUser;
use Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention\{AssignTeamToInterventionCommand, AssignTeamToInterventionResult};
use Intervention\Domain\Exception\InterventionValidationException;
use Intervention\Domain\Service\InterventionTransitionPolicy;
use Intervention\Presentation\Api\Dto\Input\AssignInterventionTeamInput;
use Intervention\Presentation\Api\Factory\InterventionOutputFactory;
use Intervention\Presentation\Api\Processor\Assignment\AssignTeamToInterventionProcessor;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Presentation\Api\Http\RevisionGuard;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, PreconditionRequiredHttpException, UnprocessableEntityHttpException};

#[CoversClass(AssignTeamToInterventionProcessor::class)]
final class AssignTeamToInterventionProcessorTest extends TestCase
{
  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const string TEAM_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c20';

  #[Test]
  public function testProcessDispatchesCommandWithTheAuthenticatedUser(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static fn (AssignTeamToInterventionCommand $command): bool => self::USER_ID === $command->userId
        && self::INTERVENTION_ID === $command->interventionId
        && self::TEAM_ID === $command->teamId
        // The whole point of reading If-Match here: the workflow gateway
        // refuses a null expected revision with 428, so an assignment that
        // does not forward it can never succeed.
        && 3 === $command->expectedRevision))
      ->willReturn(new AssignTeamToInterventionResult(null));

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard('"revision-3"'),
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $output = $processor->process($input, new Post(), ['id' => self::INTERVENTION_ID]);

    self::assertNull($output);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenThePayloadIsNotATeamAssignment(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard('"revision-3"'),
    );

    $this->expectException(BadRequestHttpException::class);

    $processor->process(null, new Post(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProcessThrowsBadRequestWhenInterventionIdMissing(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard('"revision-3"'),
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $this->expectException(BadRequestHttpException::class);

    $processor->process($input, new Post(), []);
  }

  #[Test]
  public function testProcessThrowsUnauthorizedWithoutAnAuthenticatedUser(): void
  {
    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn(null);

    $processor = new AssignTeamToInterventionProcessor(
      $this->createStub(CommandBusPort::class),
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard('"revision-3"'),
    );

    $this->expectException(AccessDeniedHttpException::class);

    $processor->process(new AssignInterventionTeamInput(), new Post(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProcessMapsAnEmptyTeamToUnprocessableEntity(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->willThrowException(new InterventionValidationException('The team has no active members to assign.'));

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard('"revision-3"'),
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['id' => self::INTERVENTION_ID]);
  }

  #[Test]
  public function testProcessRefusesAnAssignmentWithoutAnIfMatchHeader(): void
  {
    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
      $this->revisionGuard(null),
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $this->expectException(PreconditionRequiredHttpException::class);

    $processor->process($input, new Post(), ['id' => self::INTERVENTION_ID]);
  }

  /**
   * A real {@see RevisionGuard} over a request carrying (or omitting) the
   * `If-Match` header. The guard is `final readonly`, so there is nothing to
   * double — and nothing worth doubling: the header parsing IS the contract
   * under test here.
   *
   * @param ?string $ifMatch the raw header value, or null to omit it entirely
   */
  private function revisionGuard(?string $ifMatch): RevisionGuard
  {
    $request = Request::create('/api/interventions/' . self::INTERVENTION_ID . '/team-assignments', 'POST');
    if (null !== $ifMatch) {
      $request->headers->set('If-Match', $ifMatch);
    }

    $requestStack = new RequestStack();
    $requestStack->push($request);

    return new RevisionGuard($requestStack);
  }

  private function securityUser(): SecurityUser
  {
    return new SecurityUser(
      id: self::USER_ID,
      email: 'user@example.com',
      password: 'hashed-password',
      roles: ['ROLE_USER'],
      scopes: [],
      isActive: true,
    );
  }
}
