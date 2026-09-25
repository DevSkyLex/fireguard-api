<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Approval\Application\UseCase\Query\Request\GetApprovalRequest\GetApprovalRequestResult;
use Approval\Domain\Model\ApprovalRequest\{ApprovalRequest, ApprovalRequestCreation, ApprovalRequestSchedule, ApprovalRequestSubmission};
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetApprovalRequestResult.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetApprovalRequestResult::class)]
final class GetApprovalRequestResultTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  #[Test]
  public function testFromDomainMapsPendingAggregate(): void
  {
    $expiresAt = new DateTimeImmutable('2026-02-01T00:00:00+00:00');
    $now = new DateTimeImmutable('2026-01-18T00:00:00+00:00');

    $request = ApprovalRequest::create(new ApprovalRequestCreation(
      ApprovalRequestId::fromString(self::REQUEST_ID),
      'org-1',
      'nc_waiver',
      'nc-1',
      new ApprovalRequestSubmission('member-1', 'user-1', ['nonConformityId' => 'nc-1']),
      new ApprovalRequestSchedule($expiresAt, $now),
    ));

    $result = GetApprovalRequestResult::fromDomain($request);

    self::assertSame(self::REQUEST_ID, $result->id);
    self::assertSame('org-1', $result->organizationId);
    self::assertSame('nc_waiver', $result->actionType);
    self::assertSame('nc-1', $result->subjectId);
    self::assertSame('pending', $result->status);
    self::assertSame('member-1', $result->requestedByMemberId);
    self::assertSame('user-1', $result->requestedByUserId);
    self::assertNull($result->decisionByMemberId);
    self::assertNull($result->decisionByUserId);
    self::assertNull($result->decisionNote);
    self::assertSame($expiresAt, $result->expiresAt);
    self::assertSame($now, $result->createdAt);
    self::assertSame($now, $result->updatedAt);
    self::assertNull($result->decidedAt);
    self::assertNull($result->executedAt);
    self::assertNull($result->executionError);
  }
}
