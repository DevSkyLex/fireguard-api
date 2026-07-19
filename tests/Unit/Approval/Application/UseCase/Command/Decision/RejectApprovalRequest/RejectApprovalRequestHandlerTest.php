<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\RejectApprovalRequest;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\{RejectApprovalRequestCommand, RejectApprovalRequestHandler};
use Approval\Domain\Event\Request\ApprovalRejectedEvent;
use Approval\Domain\Exception\{ApprovalRequestNotFoundException, SelfApprovalNotAllowedException};
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

/**
 * Test RejectApprovalRequestHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RejectApprovalRequestHandler::class)]
final class RejectApprovalRequestHandlerTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d01';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d02';

  private const string REQUESTER_MEMBER_ID = 'requester-member';

  private const string APPROVER_MEMBER_ID = 'approver-member';

  #[Test]
  public function testInvokeThrowsNotFoundWhenRequestBelongsToDifferentOrganization(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);

    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler($requests)(new RejectApprovalRequestCommand('different-org', self::REQUEST_ID, self::APPROVER_MEMBER_ID));
  }

  #[Test]
  public function testInvokeThrowsSelfApprovalNotAllowedWhenRejecterIsRequester(): void
  {
    $request = $this->pendingRequest();

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::REQUESTER_MEMBER_ID);
    $memberDirectory->method('memberSatisfiesRole')->willReturn(true);

    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::policy());

    $this->expectException(SelfApprovalNotAllowedException::class);

    $this->handler($requests, $memberDirectory, $policy)(
      new RejectApprovalRequestCommand(self::ORG_ID, self::REQUEST_ID, self::REQUESTER_MEMBER_ID),
    );
  }

  #[Test]
  public function testInvokeRejectsAndDispatchesRejectedEventWithoutExecutingAnything(): void
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

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ApprovalRejectedEvent::class));

    $result = $this->handler($requests, $memberDirectory, $policy, eventDispatcher: $eventDispatcher)(
      new RejectApprovalRequestCommand(self::ORG_ID, self::REQUEST_ID, self::APPROVER_MEMBER_ID, 'not justified'),
    );

    self::assertSame('rejected', $result->status);
    self::assertSame('not justified', $result->decisionNote);
    self::assertFalse($request->isPending());
    self::assertNull($request->executedAt());
  }

  private function pendingRequest(): ApprovalRequest
  {
    return ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::REQUEST_ID),
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: self::REQUESTER_MEMBER_ID,
      requestedByUserId: 'requester-user',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );
  }

  private static function policy(): ApprovalPolicy
  {
    return new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical']],
      allowSelfApproval: false,
      approvalTtlDays: 14,
    );
  }

  private function handler(
    ApprovalRequestRepositoryPort $requests,
    ?ApprovalMemberDirectoryPort $memberDirectory = null,
    ?ApprovalPolicyPort $policy = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): RejectApprovalRequestHandler {
    $memberDirectory ??= $this->createStub(ApprovalMemberDirectoryPort::class);
    $policy ??= $this->createStub(ApprovalPolicyPort::class);
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-20T00:00:00+00:00'));

    return new RejectApprovalRequestHandler(
      requests: $requests,
      policy: $policy,
      memberDirectory: $memberDirectory,
      authorization: $authorization,
      eventDispatcher: $eventDispatcher,
      clock: $clock,
    );
  }
}
