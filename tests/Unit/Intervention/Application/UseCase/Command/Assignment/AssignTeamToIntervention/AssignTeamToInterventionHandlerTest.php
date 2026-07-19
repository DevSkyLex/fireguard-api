<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention;

use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\InterventionWorkflowGatewayPort;
use Intervention\Application\UseCase\Command\Assignment\AssignTeamToIntervention\{AssignTeamToInterventionCommand, AssignTeamToInterventionHandler};
use Intervention\Application\UseCase\Command\Workflow\MutateInterventionWorkflow\{MutateInterventionWorkflowCommand, MutateInterventionWorkflowResult};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, TeamDirectoryPort};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;

#[CoversClass(AssignTeamToInterventionHandler::class)]
final class AssignTeamToInterventionHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const string TEAM_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c20';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  private const string EXISTING_PARTICIPANT = '018f0b68-6758-7a12-8a1d-3f0d97f63c30';

  private const string TEAM_MEMBER_A = '018f0b68-6758-7a12-8a1d-3f0d97f63c31';

  private const string TEAM_MEMBER_B = '018f0b68-6758-7a12-8a1d-3f0d97f63c32';

  #[Test]
  public function testInvokeUnionsExistingAndActiveTeamMembersDeduped(): void
  {
    $context = new InterventionWorkflowContext(
      self::INTERVENTION_ID,
      self::ORGANIZATION_ID,
      'draft',
      null,
      [self::EXISTING_PARTICIPANT, self::TEAM_MEMBER_A],
    );

    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())->method('interventionContext')->with(self::INTERVENTION_ID)->willReturn($context);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $teamDirectory = $this->createMock(TeamDirectoryPort::class);
    $teamDirectory->expects(self::once())
      ->method('listActiveMemberIds')
      ->with(self::ORGANIZATION_ID, self::TEAM_ID)
      ->willReturn([self::TEAM_MEMBER_A, self::TEAM_MEMBER_B]);

    $view = new InterventionWorkflowView('intervention', self::ORGANIZATION_ID, ['id' => self::INTERVENTION_ID]);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (MutateInterventionWorkflowCommand $command): bool {
        return 'intervention' === $command->resource
          && 'update' === $command->action
          && self::USER_ID === $command->userId
          && self::INTERVENTION_ID === $command->id
          && null === $command->expectedRevision
          && [self::EXISTING_PARTICIPANT, self::TEAM_MEMBER_A, self::TEAM_MEMBER_B] === $command->payload['participants'];
      }))
      ->willReturn(new MutateInterventionWorkflowResult($view));

    $handler = new AssignTeamToInterventionHandler($gateway, $authorization, $teamDirectory, $commandBus);

    $result = $handler->__invoke(new AssignTeamToInterventionCommand(
      userId: self::USER_ID,
      interventionId: self::INTERVENTION_ID,
      teamId: self::TEAM_ID,
    ));

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function testInvokeThrowsWhenInterventionNotFound(): void
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn(null);

    $handler = new AssignTeamToInterventionHandler(
      $gateway,
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(TeamDirectoryPort::class),
      $this->createStub(CommandBusPort::class),
    );

    $this->expectException(InterventionNotFoundException::class);

    $handler->__invoke(new AssignTeamToInterventionCommand(self::USER_ID, self::INTERVENTION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testInvokeThrowsWhenPermissionIsMissing(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft', null, []);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $handler = new AssignTeamToInterventionHandler(
      $gateway,
      $authorization,
      $this->createStub(TeamDirectoryPort::class),
      $commandBus,
    );

    $this->expectException(InterventionAccessDeniedException::class);

    $handler->__invoke(new AssignTeamToInterventionCommand(self::USER_ID, self::INTERVENTION_ID, self::TEAM_ID));
  }

  #[Test]
  public function testInvokeRejectsAnEmptyTeam(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'draft', null, []);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $teamDirectory = $this->createStub(TeamDirectoryPort::class);
    $teamDirectory->method('listActiveMemberIds')->willReturn([]);

    /** @var CommandBusPort&MockObject $commandBus */
    $commandBus = $this->createMock(CommandBusPort::class);
    $commandBus->expects(self::never())->method('dispatch');

    $handler = new AssignTeamToInterventionHandler($gateway, $authorization, $teamDirectory, $commandBus);

    $this->expectException(InterventionValidationException::class);

    $handler->__invoke(new AssignTeamToInterventionCommand(self::USER_ID, self::INTERVENTION_ID, self::TEAM_ID));
  }
}
