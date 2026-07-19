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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\{AccessDeniedHttpException, BadRequestHttpException, UnprocessableEntityHttpException};

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
        && self::TEAM_ID === $command->teamId))
      ->willReturn(new AssignTeamToInterventionResult(null));

    $security = $this->createStub(Security::class);
    $security->method('getUser')->willReturn($this->securityUser());

    $processor = new AssignTeamToInterventionProcessor(
      $commandBus,
      new InterventionOutputFactory(new InterventionTransitionPolicy()),
      $security,
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $output = $processor->process($input, new Post(), ['id' => self::INTERVENTION_ID]);

    self::assertNull($output);
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
    );

    $input = new AssignInterventionTeamInput();
    $input->teamId = self::TEAM_ID;

    $this->expectException(UnprocessableEntityHttpException::class);

    $processor->process($input, new Post(), ['id' => self::INTERVENTION_ID]);
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
