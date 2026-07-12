<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Activity\AddInterventionComment;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\InterventionMemberPolicy;
use Intervention\Application\UseCase\Command\Activity\AddInterventionComment\{AddInterventionCommentCommand, AddInterventionCommentHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId};
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test AddInterventionCommentHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(AddInterventionCommentHandler::class)]
final class AddInterventionCommentHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c11';

  private const string INTERVENTION_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c10';

  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c13';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c14';

  #[Test]
  public function itAppendsATrimmedCommentAttributedToTheActiveMember(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->expects(self::once())->method('interventionContext')->with(self::INTERVENTION_ID)->willReturn($context);

    $view = new InterventionWorkflowView('activity', self::ORGANIZATION_ID, ['id' => 'activity-1']);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::once())
      ->method('append')
      ->with(self::INTERVENTION_ID, self::ORGANIZATION_ID, self::MEMBER_ID, 'comment', 'comment', 'Trimmed body', null)
      ->willReturn($view);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $result = new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, '  Trimmed body  '),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itThrowsWhenTheInterventionCannotBeFound(): void
  {
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn(null);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheReadPermission(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $this->expectException(InterventionAccessDeniedException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itRejectsABlankCommentBody(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $this->expectException(InterventionValidationException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, '   '),
    );
  }

  #[Test]
  public function itRejectsAUserWhoIsNotAnOrganizationMember(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createMock(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $repository = $this->createMock(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn(null);

    $this->expectException(InterventionConflictException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, new InterventionMemberPolicy($repository))(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  private function memberPolicy(): InterventionMemberPolicy
  {
    $member = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::USER_ID,
      true,
      new DateTimeImmutable(),
    );
    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn($member);
    $repository->method('findById')->willReturn($member);

    return new InterventionMemberPolicy($repository);
  }
}
