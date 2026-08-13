<?php

declare(strict_types=1);

namespace Tests\Unit\Intervention\Application\UseCase\Command\Activity\AddInterventionComment;

use DateTimeImmutable;
use Intervention\Application\Contract\Workflow\{InterventionWorkflowContext, InterventionWorkflowView};
use Intervention\Application\Port\Outbound\{InterventionActivityPort, InterventionWorkflowGatewayPort};
use Intervention\Application\Service\{InterventionMemberPolicy, InterventionNotificationService, InterventionReviewerRecipientResolver};
use Intervention\Application\UseCase\Command\Activity\AddInterventionComment\{AddInterventionCommentCommand, AddInterventionCommentHandler};
use Intervention\Domain\Exception\{InterventionAccessDeniedException, InterventionConflictException, InterventionNotFoundException, InterventionValidationException};
use Notification\Application\Contract\Notification\{NotificationChannel, SendNotificationRequest, SentNotification};
use Notification\Application\Port\Inbound\NotificationPort;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\{OrganizationAuthorizationPort, OrganizationNotificationPolicyPort};
use Organization\Application\Port\Outbound\OrganizationMemberRepositoryPort;
use Organization\Domain\Model\OrganizationMember\OrganizationMember;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationMemberId, OrganizationNotificationSettings};
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

  private const string MENTIONED_MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c15';

  private const string MENTIONED_USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f63c16';

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
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $result = new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, '  Trimmed body  '),
    );

    self::assertSame($view, $result->view);
  }

  #[Test]
  public function itThrowsWhenTheInterventionCannotBeFound(): void
  {
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn(null);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);

    $this->expectException(InterventionNotFoundException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itRejectsAUserMissingTheReadPermission(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::MISSING_PERMISSION);

    $this->expectException(InterventionAccessDeniedException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itReportsAnOutOfScopeCallerAsNotFound(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::OUTSIDE_SCOPE);

    // The same exception itThrowsWhenTheInterventionCannotBeFound asserts: a
    // caller from another organization must not learn this id is real.
    $this->expectException(InterventionNotFoundException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itRejectsABlankCommentBody(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $this->expectException(InterventionValidationException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, '   '),
    );
  }

  #[Test]
  public function itRejectsAUserWhoIsNotAnOrganizationMember(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);
    $activities = $this->createMock(InterventionActivityPort::class);
    $activities->expects(self::never())->method('append');
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $repository = $this->createStub(OrganizationMemberRepositoryPort::class);
    $repository->method('findByOrganizationAndUser')->willReturn(null);

    $this->expectException(InterventionConflictException::class);

    new AddInterventionCommentHandler($gateway, $activities, $authorization, new InterventionMemberPolicy($repository), $this->notificationService())(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, 'A comment'),
    );
  }

  #[Test]
  public function itNotifiesMentionedMembersOnceEachButNeverTheAuthor(): void
  {
    $context = new InterventionWorkflowContext(self::INTERVENTION_ID, self::ORGANIZATION_ID, 'in_progress', null);
    $gateway = $this->createStub(InterventionWorkflowGatewayPort::class);
    $gateway->method('interventionContext')->willReturn($context);

    $view = new InterventionWorkflowView('activity', self::ORGANIZATION_ID, ['id' => 'activity-1']);
    $activities = $this->createStub(InterventionActivityPort::class);
    $activities->method('append')->willReturn($view);

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn(OrganizationAccessDecision::GRANTED);

    $notificationPort = $this->createMock(NotificationPort::class);
    $notificationPort->expects(self::once())
      ->method('send')
      ->with(self::callback(static fn (SendNotificationRequest $request): bool => 'intervention.comment_mention' === $request->type
        && [NotificationChannel::MERCURE, NotificationChannel::EMAIL] === $request->channels
        && self::MENTIONED_USER_ID === $request->recipientUserId
        && self::INTERVENTION_ID === $request->payload['interventionId']))
      ->willReturn(new SentNotification(
        'notification-1',
        'intervention.comment_mention',
        'Mentioned in a comment',
        'A teammate mentioned you in an intervention comment.',
        [NotificationChannel::MERCURE->value, NotificationChannel::EMAIL->value],
        [],
        [NotificationChannel::MERCURE->value => true, NotificationChannel::EMAIL->value => true],
        new DateTimeImmutable(),
        self::MENTIONED_USER_ID,
        null,
      ));

    $body = 'Ping @{' . self::MENTIONED_MEMBER_ID . '} (bis: @{' . self::MENTIONED_MEMBER_ID . '}) et moi-même @{' . self::MEMBER_ID . '}.';

    new AddInterventionCommentHandler($gateway, $activities, $authorization, $this->memberPolicy(), $this->notificationService($notificationPort))(
      new AddInterventionCommentCommand(self::USER_ID, self::INTERVENTION_ID, $body),
    );
  }

  /**
   * Builds a real notification service around a stub (or mock) notification
   * port: the service class is final, so tests compose it instead of mocking.
   */
  private function notificationService(?NotificationPort $port = null): InterventionNotificationService
  {
    $mentioned = OrganizationMember::reconstitute(
      OrganizationMemberId::fromString(self::MENTIONED_MEMBER_ID),
      OrganizationId::fromString(self::ORGANIZATION_ID),
      self::MENTIONED_USER_ID,
      true,
      new DateTimeImmutable(),
    );
    $members = $this->createStub(OrganizationMemberRepositoryPort::class);
    $members->method('findById')->willReturn($mentioned);

    $policy = $this->createStub(OrganizationNotificationPolicyPort::class);
    $policy->method('notificationPolicy')->willReturn(new OrganizationNotificationSettings());

    $reviewerMembers = $this->createStub(OrganizationMemberRepositoryPort::class);
    $reviewerMembers->method('findByOrganizationId')->willReturn([]);
    $reviewers = new InterventionReviewerRecipientResolver(
      $reviewerMembers,
      $this->createStub(OrganizationAuthorizationPort::class),
    );

    return new InterventionNotificationService(
      $port ?? $this->createStub(NotificationPort::class),
      $members,
      $policy,
      $reviewers,
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
