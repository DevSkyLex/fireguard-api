<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Service;

use Approval\Application\Contract\Gate\{ApprovalGateDecision, ApprovalGateRequest};
use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Contract\Reservation\ApprovalReservation;
use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort, ApprovalRequestRepositoryPort};
use Approval\Application\Service\ApprovalGate;
use Approval\Domain\Event\Request\ApprovalRequestedEvent;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Domain\Exception\OrganizationAccessDeniedException;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Factory\UuidFactory;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort, UuidGeneratorPort};

/**
 * Test ApprovalGateTest.
 *
 * @category Application Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalGate::class)]
final class ApprovalGateTest extends TestCase
{
  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string USER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b02';

  private const string MEMBER_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b03';

  private const string GENERATED_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b04';

  #[Test]
  public function testEvaluateReturnsApplyNowWhenActionTypeDisabled(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::disabledPolicy());

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::never())->method('reservePending');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $decision = $this->gate($policy, $requests, eventDispatcher: $eventDispatcher)->evaluate(self::gateRequest());

    self::assertFalse($decision->deferred);
  }

  #[Test]
  public function testEvaluateReturnsApplyNowWhenSeverityBelowThreshold(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical']],
      allowSelfApproval: false,
      approvalTtlDays: 14,
    ));

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::never())->method('reservePending');

    $decision = $this->gate($policy, $requests)->evaluate(new ApprovalGateRequest(
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByUserId: self::USER_ID,
      payload: ['severity' => 'low'],
    ));

    self::assertFalse($decision->deferred);
  }

  #[Test]
  public function testEvaluateDeniesWhenRequesterLacksApprovalsRequestPermission(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::enabledPolicy());

    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('assertGrantedPermissions')->willThrowException(
      OrganizationAccessDeniedException::missingPermission('organization.approvals.request'),
    );

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::never())->method('reservePending');

    $this->expectException(OrganizationAccessDeniedException::class);

    $this->gate($policy, $requests, authorization: $authorization)->evaluate(self::gateRequest());
  }

  #[Test]
  public function testEvaluateReservesAndDispatchesRequestedEventOnNewReservation(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::enabledPolicy());

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::MEMBER_ID);

    /** @var ApprovalRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::once())
      ->method('reservePending')
      ->willReturn(new ApprovalReservation(self::GENERATED_ID, true));

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ApprovalRequestedEvent $event): bool {
        self::assertSame(self::ORG_ID, $event->organizationId);
        self::assertSame(self::GENERATED_ID, $event->requestId);
        self::assertSame('nc_waiver', $event->actionType);
        self::assertSame(self::MEMBER_ID, $event->requestedByMemberId);

        return true;
      }));

    $decision = $this->gate($policy, $requests, $memberDirectory, eventDispatcher: $eventDispatcher)->evaluate(self::gateRequest());

    self::assertTrue($decision->deferred);
    self::assertSame(self::GENERATED_ID, $decision->requestId);
    self::assertSame('pending', $decision->status);
  }

  #[Test]
  public function testEvaluateReturnsExistingPendingRequestOnConflictWithoutDispatchingEvent(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::enabledPolicy());

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::MEMBER_ID);

    $existingExpiresAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $existing = ApprovalRequest::create(
      id: ApprovalRequestId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64b05'),
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: self::MEMBER_ID,
      requestedByUserId: self::USER_ID,
      payload: [],
      expiresAt: $existingExpiresAt,
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('reservePending')->willReturn(new ApprovalReservation((string) $existing->id(), false));
    $requests->method('findById')->willReturn($existing);

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $decision = $this->gate($policy, $requests, $memberDirectory, eventDispatcher: $eventDispatcher)->evaluate(self::gateRequest());

    self::assertTrue($decision->deferred);
    self::assertSame((string) $existing->id(), $decision->requestId);
    self::assertEquals($existingExpiresAt, $decision->expiresAt);
  }

  #[Test]
  public function testEvaluateThrowsWhenRequesterIsNotAnOrganizationMember(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(self::enabledPolicy());

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(null);

    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::never())->method('reservePending');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $this->expectException(OrganizationAccessDeniedException::class);
    $this->expectExceptionMessage('Missing organization.approvals.request permission.');

    $this->gate($policy, $requests, $memberDirectory, eventDispatcher: $eventDispatcher)->evaluate(self::gateRequest());
  }

  #[Test]
  public function testEvaluateDispatchesEventAndClampsTtlWhenConflictingRequestIsNoLongerFound(): void
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => null]],
      allowSelfApproval: false,
      approvalTtlDays: 0,
    ));

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::MEMBER_ID);

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('reservePending')->willReturn(new ApprovalReservation(self::GENERATED_ID, false));
    $requests->method('findById')->willReturn(null);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::callback(static function (ApprovalRequestedEvent $event): bool {
        self::assertSame(self::GENERATED_ID, $event->requestId);

        return true;
      }));

    $decision = $this->gate($policy, $requests, $memberDirectory, eventDispatcher: $eventDispatcher)->evaluate(self::gateRequest());

    self::assertTrue($decision->deferred);
    self::assertSame(self::GENERATED_ID, $decision->requestId);
    self::assertSame('pending', $decision->status);
    self::assertEquals(new DateTimeImmutable('2026-01-19T00:00:00+00:00'), $decision->expiresAt);
  }

  #[Test]
  public function testEvaluateRequiresApprovalWhenSeverityIsNotAString(): void
  {
    $decision = $this->deferredDecisionForPayload(minSeverity: 'high', payload: ['severity' => 123]);

    self::assertTrue($decision->deferred);
    self::assertSame('pending', $decision->status);
  }

  #[Test]
  public function testEvaluateRequiresApprovalWhenSeverityIsUnrecognized(): void
  {
    $decision = $this->deferredDecisionForPayload(minSeverity: 'high', payload: ['severity' => 'catastrophic']);

    self::assertTrue($decision->deferred);
    self::assertSame('pending', $decision->status);
  }

  #[Test]
  public function testEvaluateRequiresApprovalWhenThresholdSeverityIsUnrecognized(): void
  {
    $decision = $this->deferredDecisionForPayload(minSeverity: 'extreme', payload: ['severity' => 'low']);

    self::assertTrue($decision->deferred);
    self::assertSame('pending', $decision->status);
  }

  /**
   * Method deferredDecisionForPayload.
   *
   * Drives {@see ApprovalGate::evaluate()} through the fail-closed severity
   * branch (approval required) for the given threshold and payload.
   *
   * @param string $minSeverity the policy's minimum severity threshold
   * @param array<string, mixed> $payload the gate request payload
   *
   * @return ApprovalGateDecision the resulting decision
   */
  private function deferredDecisionForPayload(string $minSeverity, array $payload): ApprovalGateDecision
  {
    $policy = $this->createStub(ApprovalPolicyPort::class);
    $policy->method('policyFor')->willReturn(new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => $minSeverity]],
      allowSelfApproval: false,
      approvalTtlDays: 14,
    ));

    $memberDirectory = $this->createStub(ApprovalMemberDirectoryPort::class);
    $memberDirectory->method('resolveMemberId')->willReturn(self::MEMBER_ID);

    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('reservePending')->willReturn(new ApprovalReservation(self::GENERATED_ID, true));

    return $this->gate($policy, $requests, $memberDirectory)->evaluate(new ApprovalGateRequest(
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByUserId: self::USER_ID,
      payload: $payload,
    ));
  }

  private function gate(
    ApprovalPolicyPort $policy,
    ApprovalRequestRepositoryPort $requests,
    ?ApprovalMemberDirectoryPort $memberDirectory = null,
    ?OrganizationAuthorizationPort $authorization = null,
    ?EventDispatcherPort $eventDispatcher = null,
  ): ApprovalGate {
    $memberDirectory ??= $this->createStub(ApprovalMemberDirectoryPort::class);
    $authorization ??= $this->createStub(OrganizationAuthorizationPort::class);
    $eventDispatcher ??= $this->createStub(EventDispatcherPort::class);

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-18T00:00:00+00:00'));

    $uuidGenerator = $this->createStub(UuidGeneratorPort::class);
    $uuidGenerator->method('generate')->willReturn(self::GENERATED_ID);

    return new ApprovalGate(
      policy: $policy,
      requests: $requests,
      memberDirectory: $memberDirectory,
      authorization: $authorization,
      eventDispatcher: $eventDispatcher,
      clock: $clock,
      uuidFactory: new UuidFactory($uuidGenerator),
    );
  }

  private static function gateRequest(): ApprovalGateRequest
  {
    return new ApprovalGateRequest(
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByUserId: self::USER_ID,
      payload: ['severity' => 'critical'],
    );
  }

  private static function enabledPolicy(): ApprovalPolicy
  {
    return new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => true, 'minApproverRole' => 'admin', 'minSeverity' => 'critical']],
      allowSelfApproval: false,
      approvalTtlDays: 14,
    );
  }

  private static function disabledPolicy(): ApprovalPolicy
  {
    return new ApprovalPolicy(
      actionRules: ['nc_waiver' => ['enabled' => false, 'minApproverRole' => 'admin', 'minSeverity' => null]],
      allowSelfApproval: false,
      approvalTtlDays: 14,
    );
  }
}
