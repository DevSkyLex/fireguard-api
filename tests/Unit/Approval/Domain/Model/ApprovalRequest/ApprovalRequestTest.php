<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Domain\Model\ApprovalRequest;

use Approval\Domain\Exception\ApprovalRequestNotPendingException;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\{ApprovalRequestId, ApprovalStatus};
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApprovalRequestTest.
 *
 * @category Domain Model Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApprovalRequest::class)]
final class ApprovalRequestTest extends TestCase
{
  private const string ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b01';

  private const string ORG_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64b02';

  #[Test]
  public function testCreateStartsPending(): void
  {
    $request = $this->makeRequest();

    self::assertTrue($request->isPending());
    self::assertSame(ApprovalStatus::PENDING, $request->status());
    self::assertNull($request->decisionByMemberId());
    self::assertNull($request->executedAt());
  }

  #[Test]
  public function testApproveTransitionsToApprovedAndRecordsDecision(): void
  {
    $request = $this->makeRequest();
    $decidedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $request->approve('approver-member', 'approver-user', 'looks fine', $decidedAt);

    self::assertSame(ApprovalStatus::APPROVED, $request->status());
    self::assertFalse($request->isPending());
    self::assertSame('approver-member', $request->decisionByMemberId());
    self::assertSame('approver-user', $request->decisionByUserId());
    self::assertSame('looks fine', $request->decisionNote());
    self::assertEquals($decidedAt, $request->decidedAt());
    self::assertEquals($decidedAt, $request->updatedAt());
  }

  #[Test]
  public function testApproveTwiceThrowsNotPending(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $request->approve('approver-member', 'approver-user', null, $now);

    $this->expectException(ApprovalRequestNotPendingException::class);
    $request->approve('approver-member', 'approver-user', null, $now);
  }

  #[Test]
  public function testRejectTransitionsToRejected(): void
  {
    $request = $this->makeRequest();
    $decidedAt = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $request->reject('approver-member', 'approver-user', 'not justified', $decidedAt);

    self::assertSame(ApprovalStatus::REJECTED, $request->status());
    self::assertSame('not justified', $request->decisionNote());
    self::assertEquals($decidedAt, $request->decidedAt());
  }

  #[Test]
  public function testRejectOnNonPendingThrows(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $request->reject('approver-member', 'approver-user', null, $now);

    $this->expectException(ApprovalRequestNotPendingException::class);
    $request->reject('approver-member', 'approver-user', null, $now);
  }

  #[Test]
  public function testCancelTransitionsToCancelledAndRecordsReasonInExecutionError(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $request->cancel('subject changed', $now);

    self::assertSame(ApprovalStatus::CANCELLED, $request->status());
    self::assertSame('subject changed', $request->executionError());
  }

  #[Test]
  public function testCancelOnNonPendingThrows(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $request->cancel('first reason', $now);

    $this->expectException(ApprovalRequestNotPendingException::class);
    $request->cancel('second reason', $now);
  }

  #[Test]
  public function testExpireTransitionsToExpired(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $request->expire($now);

    self::assertSame(ApprovalStatus::EXPIRED, $request->status());
    self::assertFalse($request->isPending());
  }

  #[Test]
  public function testExpireOnNonPendingThrows(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $request->expire($now);

    $this->expectException(ApprovalRequestNotPendingException::class);
    $request->expire($now);
  }

  #[Test]
  public function testMarkExecutedRecordsTimestampAndClearsError(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');
    $request->markExecutionFailed('transient failure', $now);

    $executedAt = new DateTimeImmutable('2026-02-01T10:05:00+00:00');
    $request->markExecuted($executedAt);

    self::assertEquals($executedAt, $request->executedAt());
    self::assertNull($request->executionError());
  }

  #[Test]
  public function testMarkExecutionFailedRecordsErrorWithoutChangingStatus(): void
  {
    $request = $this->makeRequest();
    $now = new DateTimeImmutable('2026-02-01T10:00:00+00:00');

    $request->markExecutionFailed('boom', $now);

    self::assertSame('boom', $request->executionError());
    self::assertTrue($request->isPending());
  }

  #[Test]
  public function testReconstituteRoundTripsEveryField(): void
  {
    $createdAt = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $updatedAt = new DateTimeImmutable('2026-01-02T00:00:00+00:00');
    $expiresAt = new DateTimeImmutable('2026-01-15T00:00:00+00:00');
    $decidedAt = new DateTimeImmutable('2026-01-03T00:00:00+00:00');
    $executedAt = new DateTimeImmutable('2026-01-03T00:01:00+00:00');

    $request = ApprovalRequest::reconstitute(
      id: ApprovalRequestId::fromString(self::ID),
      organizationId: self::ORG_ID,
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      status: ApprovalStatus::APPROVED,
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      decisionByMemberId: 'member-2',
      decisionByUserId: 'user-2',
      decisionNote: 'ok',
      payload: ['organizationId' => self::ORG_ID, 'equipmentId' => 'equip-1'],
      expiresAt: $expiresAt,
      createdAt: $createdAt,
      updatedAt: $updatedAt,
      decidedAt: $decidedAt,
      executedAt: $executedAt,
      executionError: null,
    );

    self::assertSame(self::ID, (string) $request->id());
    self::assertSame(self::ORG_ID, $request->organizationId());
    self::assertSame('equipment_decommission', $request->actionType());
    self::assertSame('equip-1', $request->subjectId());
    self::assertSame(ApprovalStatus::APPROVED, $request->status());
    self::assertSame('member-1', $request->requestedByMemberId());
    self::assertSame('user-1', $request->requestedByUserId());
    self::assertSame('member-2', $request->decisionByMemberId());
    self::assertSame('ok', $request->decisionNote());
    self::assertSame(['organizationId' => self::ORG_ID, 'equipmentId' => 'equip-1'], $request->payload());
    self::assertEquals($expiresAt, $request->expiresAt());
    self::assertEquals($createdAt, $request->createdAt());
    self::assertEquals($decidedAt, $request->decidedAt());
    self::assertEquals($executedAt, $request->executedAt());
  }

  private function makeRequest(): ApprovalRequest
  {
    $now = new DateTimeImmutable('2026-01-01T00:00:00+00:00');

    return ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::ID),
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: 'requester-member',
      requestedByUserId: 'requester-user',
      payload: ['organizationId' => self::ORG_ID, 'inspectionId' => 'insp-1', 'nonConformityId' => 'nc-1', 'severity' => 'critical'],
      expiresAt: $now->modify('+14 days'),
      now: $now,
    );
  }
}
