<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest;

use Approval\Application\UseCase\Command\Decision\ApproveApprovalRequest\ApproveApprovalRequestResult;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ApproveApprovalRequestResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ApproveApprovalRequestResult::class)]
final class ApproveApprovalRequestResultTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  #[Test]
  public function testFromDomainMapsApprovedAndExecutedAggregate(): void
  {
    $decidedAt = new DateTimeImmutable('2026-01-20T00:00:00+00:00');

    $request = ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::REQUEST_ID),
      organizationId: 'org-1',
      actionType: 'equipment_decommission',
      subjectId: 'equip-1',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );
    $request->approve('approver-member', 'approver-user', 'approved', $decidedAt);
    $request->markExecuted($decidedAt);

    $result = ApproveApprovalRequestResult::fromDomain($request);

    self::assertSame(self::REQUEST_ID, $result->id);
    self::assertSame('approved', $result->status);
    self::assertSame('approver-member', $result->decisionByMemberId);
    self::assertSame('approver-user', $result->decisionByUserId);
    self::assertSame('approved', $result->decisionNote);
    self::assertSame($decidedAt, $result->decidedAt);
    self::assertSame($decidedAt, $result->executedAt);
    self::assertNull($result->executionError);
  }
}
