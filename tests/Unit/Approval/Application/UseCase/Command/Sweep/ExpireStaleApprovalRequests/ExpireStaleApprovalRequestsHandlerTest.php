<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests\{
  ExpireStaleApprovalRequestsCommand,
  ExpireStaleApprovalRequestsHandler
};
use Approval\Domain\Event\Request\ApprovalExpiredEvent;
use Approval\Domain\Model\ApprovalRequest\{ApprovalRequest, ApprovalRequestCreation, ApprovalRequestSchedule, ApprovalRequestSubmission};
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Tests\Support\Approval\ImmediateApprovalDecisionLock;

/**
 * Test ExpireStaleApprovalRequestsHandlerTest.
 *
 * @category UseCase Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ExpireStaleApprovalRequestsHandler::class)]
final class ExpireStaleApprovalRequestsHandlerTest extends TestCase
{
  #[Test]
  public function testInvokeExpiresOnlyStalePendingRequestsAndDispatchesEvents(): void
  {
    $now = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $stale = ApprovalRequest::create(new ApprovalRequestCreation(
      ApprovalRequestId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64e01'),
      'org-1',
      'nc_waiver',
      'nc-1',
      new ApprovalRequestSubmission('member-1', 'user-1', []),
      new ApprovalRequestSchedule(new DateTimeImmutable('2026-01-15T00:00:00+00:00'), new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    ));

    /** @var ApprovalRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::once())
      ->method('findPendingExpiredBefore')
      ->with($now, self::anything())
      ->willReturn([$stale]);
    $requests->expects(self::once())->method('save')->with($stale);
    $requests->method('findById')->willReturn($stale);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ApprovalExpiredEvent::class));

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $handler = new ExpireStaleApprovalRequestsHandler($requests, $eventDispatcher, $clock, new ImmediateApprovalDecisionLock());

    $result = $handler(new ExpireStaleApprovalRequestsCommand());

    self::assertInstanceOf(VoidResult::class, $result);
    self::assertSame('expired', $stale->status()->value);
  }

  #[Test]
  public function testInvokeSkipsRequestsAlreadyDecidedByAConcurrentRun(): void
  {
    $now = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $alreadyApproved = ApprovalRequest::create(new ApprovalRequestCreation(
      ApprovalRequestId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64e02'),
      'org-1',
      'nc_waiver',
      'nc-2',
      new ApprovalRequestSubmission('member-1', 'user-1', []),
      new ApprovalRequestSchedule(new DateTimeImmutable('2026-01-15T00:00:00+00:00'), new DateTimeImmutable('2026-01-01T00:00:00+00:00')),
    ));
    $alreadyApproved->approve('approver', 'approver-user', null, $now);

    /** @var ApprovalRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findPendingExpiredBefore')->willReturn([$alreadyApproved]);
    $requests->method('findById')->willReturn($alreadyApproved);
    $requests->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $handler = new ExpireStaleApprovalRequestsHandler($requests, $eventDispatcher, $clock, new ImmediateApprovalDecisionLock());

    $handler(new ExpireStaleApprovalRequestsCommand());
  }
}
