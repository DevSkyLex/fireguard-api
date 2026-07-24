<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\RejectApprovalRequest;

use Approval\Application\UseCase\Command\Decision\RejectApprovalRequest\RejectApprovalRequestResult;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test RejectApprovalRequestResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(RejectApprovalRequestResult::class)]
final class RejectApprovalRequestResultTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  #[Test]
  public function testFromDomainMapsRejectedAggregateAndNeverExecutes(): void
  {
    $decidedAt = new DateTimeImmutable('2026-01-20T00:00:00+00:00');

    $request = ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::REQUEST_ID),
      organizationId: 'org-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );
    $request->reject('approver-member', 'approver-user', 'denied', $decidedAt);

    $result = RejectApprovalRequestResult::fromDomain($request);

    self::assertSame(self::REQUEST_ID, $result->id);
    self::assertSame('rejected', $result->status);
    self::assertSame('approver-member', $result->decisionByMemberId);
    self::assertSame('approver-user', $result->decisionByUserId);
    self::assertSame('denied', $result->decisionNote);
    self::assertSame($decidedAt, $result->decidedAt);
    self::assertNull($result->executedAt);
    self::assertNull($result->executionError);
  }
}
