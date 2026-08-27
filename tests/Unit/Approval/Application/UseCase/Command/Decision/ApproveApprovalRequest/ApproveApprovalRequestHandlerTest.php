<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest;

use Approval\Application\Contract\Execution\DeferredActionContext;
use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\{ApprovalActionExecutorPort, ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Application\Service\ApprovalActionExecutorRegistry;
use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\{ApproveApprovalRequestCommand, ApproveApprovalRequestHandler};
use Approval\Domain\Event\Request\{ApprovalApprovedEvent, ApprovalExecutionFailedEvent};
use Approval\Domain\Exception\{
  ApprovalRequestNotFoundException,
  ApprovalRequestNotPendingException,
  ApproverNotAuthorizedException,
  DeferredActionNoLongerApplicableException,
  SelfApprovalNotAllowedException
};
use Approval\Domain\Exception\ApprovalAccessDeniedException;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * Test ApproveApprovalRequestHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApproveApprovalRequestHandler::class)]
final class ApproveApprovalRequestHandlerTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c02';

  private const string REQUESTER_MEMBER_ID = 'requester-member';

  private const string APPROVER_MEMBER_ID = 'approver-member';

  private const string APPROVER_USER_ID = 'approver-user';

  #[Test]
  public function testInvokeThrowsNotFoundWhenRequestMissing(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn(null);

    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler(requests: $requests)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenRequestBelongsToAnotherOrganization(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->pendingRequest());

    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler(requests: $requests)(self::command(organizationId: 'a-different-organization'));
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedWhenMemberLacksTheDecidePermission(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->pendingRequest());

    $this->expectException(ApprovalAccessDeniedException::class);

    $this->handler(
      requests: $requests,
      authorization: $this->authorizationDeciding(OrganizationAccessDecision::MISSING_PERMISSION),
    )(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheOrganizationIsOutsideTheCallersScope(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->pendingRequest());

    // Not-found rather than access-denied: a 403 would confirm to a caller
    // from another organization that the request exists.
    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler(
      requests: $requests,
      authorization: $this->authorizationDeciding(OrganizationAccessDecision::OUTSIDE_SCOPE),
    )(self::command());
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedWhenActorIsNotAnOrganizationMember(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->pendingRequest());

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(null);

    $this->expectException(ApprovalAccessDeniedException::class);

    $this->handler(requests: $requests, memberDirectory: $memberDirectory)(self::command());
  }

  #[Test]
  public function testInvokeThrowsSelfApprovalNotAllowedWhenApproverIsRequester(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::REQUESTER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy(allowSelfApproval: false));

    $this->expectException(SelfApprovalNotAllowedException::class);

    $this->handler(requests: $requests, memberDirectory: $memberDirectory, policy: $policy)(self::command());
  }

  #[Test]
  public function testInvokeAllowsSelfApprovalWhenPolicyAllowsIt(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);
    $requests->expects(self::once())->method('save');

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::REQUESTER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy(allowSelfApproval: true));

    $executor = $this->createStub(ApprovalActionExecutorPort::class);
    $executor->method('actionType')->willReturn('equipment_decommission');

    $result = $this->handler(requests: $requests, memberDirectory: $memberDirectory, policy: $policy, executors: [$executor])(
      new ApproveApprovalRequestCommand(self::ORG_ID, self::REQUEST_ID, self::REQUESTER_MEMBER_ID),
    );

    self::assertSame('approved', $result->status);
  }

  #[Test]
  public function testInvokeThrowsApproverNotAuthorizedWhenBelowMinimumRole(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::APPROVER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(false);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy());

    $this->expectException(ApproverNotAuthorizedException::class);

    $this->handler(requests: $requests, memberDirectory: $memberDirectory, policy: $policy)(self::command());
  }

  #[Test]
  public function testInvokeThrowsNotPendingOnSecondApproval(): void
  {
    $request = $this->pendingRequest();
    $request->approve(self::APPROVER_MEMBER_ID, self::APPROVER_USER_ID, null, new DateTimeImmutable());

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::APPROVER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy());

    $this->expectException(ApprovalRequestNotPendingException::class);

    $this->handler(requests: $requests, memberDirectory: $memberDirectory, policy: $policy)(self::command());
  }

  #[Test]
  public function testInvokeExecutesActionApprovesAndDispatchesApprovedEvent(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);
    $requests->expects(self::once())->method('save')->with($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::APPROVER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy());

    $executor = $this->createMock(ApprovalActionExecutorPort::class);
    $executor->method('actionType')->willReturn('equipment_decommission');
    $executor->expects(self::once())->method('execute')->with(self::callback(static function (DeferredActionContext $context): bool {
      self::assertSame(self::ORG_ID, $context->organizationId);
      self::assertSame('equip-1', $context->subjectId);

      return true;
    }));

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ApprovalApprovedEvent::class));

    $result = $this->handler(
      requests: $requests,
      memberDirectory: $memberDirectory,
      policy: $policy,
      executors: [$executor],
      eventDispatcher: $eventDispatcher,
    )(self::command());

    self::assertSame('approved', $result->status);
    self::assertSame(self::APPROVER_MEMBER_ID, $result->decisionByMemberId);
    self::assertTrue(false === $request->isPending());
    self::assertNotNull($request->executedAt());
  }

  #[Test]
  public function testInvokeCancelsRequestAndDispatchesExecutionFailedWhenActionNoLongerApplicable(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);
    $requests->expects(self::once())->method('save')->with($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::APPROVER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy());

    $executor = $this->createStub(ApprovalActionExecutorPort::class);
    $executor->method('actionType')->willReturn('equipment_decommission');
    $executor->method('execute')->willThrowException(
      DeferredActionNoLongerApplicableException::becauseSubjectChanged('equipment already deleted'),
    );

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ApprovalExecutionFailedEvent::class));

    $this->expectException(DeferredActionNoLongerApplicableException::class);

    try {
      $this->handler(
        requests: $requests,
        memberDirectory: $memberDirectory,
        policy: $policy,
        executors: [$executor],
        eventDispatcher: $eventDispatcher,
      )(self::command());
    } finally {
      self::assertSame('cancelled', $request->status()->value);
    }
  }

  private function pendingRequest(): ApprovalRequest
  {
    return ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::REQUEST_ID),
      organizationId: self::ORG_ID,
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      requestedByMemberId: self::REQUESTER_MEMBER_ID,
      requestedByUserId: 'requester-user',
      payload: ['organizationId' => self::ORG_ID, 'equipmentId' => 'equip-1'],
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );
  }

  private static function command(string $organizationId = self::ORG_ID): ApproveApprovalRequestCommand
  {
    return new ApproveApprovalRequestCommand($organizationId, self::REQUEST_ID, self::APPROVER_USER_ID);
  }

  private static function policy(bool $allowSelfApproval = false): ApprovalPolicy
  {
    return new ApprovalPolicy(
      actionRules: ['equipment_decommission' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => null]],
      allowSelfApproval: $allowSelfApproval,
      approvalTtlDays: 14,
    );
  }

  /**
   * Builds an authorization port stub resolving every access check to the
   * given decision.
   */
  private function authorizationDeciding(OrganizationAccessDecision $decision): OrganizationAuthorizationPort
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    return $authorization;
  }

  /**
   * @param list<ApprovalActionExecutorPort> $executors
   */
  private function handler(
    ApprovalRequestRepositoryPort $requests,
    ?ApprovalPolicyPort $policy = null,
    ?ApprovalMemberDirectoryPort $memberDirectory = null,
    array $executors = [],
    ?OrganizationAuthorizationPort $authorization = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): ApproveApprovalRequestHandler {
    $policy ??= $this->createStub(ApprovalPolicyPort::class);
    $memberDirectory ??= $this->createStub(ApprovalMemberDirectoryPort::class);
    $authorization ??= $this->authorizationDeciding(OrganizationAccessDecision::GRANTED);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-20T00:00:00+00:00'));

    return new ApproveApprovalRequestHandler(
      requests: $requests,
      policy: $policy,
      memberDirectory: $memberDirectory,
      executors: new ApprovalActionExecutorRegistry($executors),
      authorization: $authorization,
      eventDispatcher: $eventDispatcher,
      clock: $clock,
    );
  }
}
