<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Application\UseCase\Command\Sweep\ExpireStaleApprovalRequests\{
  ExpireStaleApprovalRequestsCommand,
  ExpireStaleApprovalRequestsHandler
};
use Approval\Domain\Event\Request\ApprovalExpiredEvent;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shared\Application\Message\VoidResult;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};

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

    $stale = ApprovalRequest::create(
      id: ApprovalRequestId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64e01'),
      organizationId: 'org-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );

    /** @var ApprovalRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->expects(self::once())
      ->method('findPendingExpiredBefore')
      ->with($now, self::anything())
      ->willReturn([$stale]);
    $requests->expects(self::once())->method('save')->with($stale);

    /** @var EventDispatcherPort&MockObject $eventDispatcher */
    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::once())
      ->method('dispatch')
      ->with(self::isInstanceOf(ApprovalExpiredEvent::class));

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $handler = new ExpireStaleApprovalRequestsHandler($requests, $eventDispatcher, $clock);

    $result = $handler(new ExpireStaleApprovalRequestsCommand());

    self::assertInstanceOf(VoidResult::class, $result);
    self::assertSame('expired', $stale->status()->value);
  }

  #[Test]
  public function testInvokeSkipsRequestsAlreadyDecidedByAConcurrentRun(): void
  {
    $now = new DateTimeImmutable('2026-02-01T00:00:00+00:00');

    $alreadyApproved = ApprovalRequest::create(
      id: ApprovalRequestId::fromString('018f0b68-6758-7a12-8a1d-3f0d97f64e02'),
      organizationId: 'org-1',
      actionType: 'nc_waiver',
      subjectId: 'nc-2',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-01-15T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
    $alreadyApproved->approve('approver', 'approver-user', null, $now);

    /** @var ApprovalRequestRepositoryPort&MockObject $requests */
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findPendingExpiredBefore')->willReturn([$alreadyApproved]);
    $requests->expects(self::never())->method('save');

    $eventDispatcher = $this->createMock(EventDispatcherPort::class);
    $eventDispatcher->expects(self::never())->method('dispatch');

    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    $handler = new ExpireStaleApprovalRequestsHandler($requests, $eventDispatcher, $clock);

    $handler(new ExpireStaleApprovalRequestsCommand());
  }
}
