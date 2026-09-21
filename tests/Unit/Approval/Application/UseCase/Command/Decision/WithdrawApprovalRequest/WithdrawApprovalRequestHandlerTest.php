<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest;

use Approval\Application\Contract\Event\ApprovalWithdrawnEvent;
use Approval\Application\Port\Outbound\{ApprovalDecisionLockPort, ApprovalMemberDirectoryPort, ApprovalRequestRepositoryPort};
use Approval\Application\UseCase\Command\Decision\WithdrawApprovalRequest\{WithdrawApprovalRequestCommand, WithdrawApprovalRequestHandler};
use Approval\Domain\Exception\{ApprovalRequestNotFoundException, ApprovalRequestNotPendingException, ApprovalWithdrawalNotAllowedException};
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\{ClockPort, EventDispatcherPort};
use Throwable;

final class WithdrawApprovalRequestHandlerTest extends TestCase
{
  private const string ID = '018f0b68-6758-7a12-8a1d-3f0d97f64d01';

  public function testWithdrawalRecordsActorAndReasonInsideTheDecisionLock(): void
  {
    $now = new DateTimeImmutable();
    $request = $this->request($now);
    $inside = false;
    $lock = $this->createMock(ApprovalDecisionLockPort::class);
    $lock->expects(self::once())->method('synchronized')->with(self::ID, self::anything())->willReturnCallback(
      static function (string $id, callable $action) use (&$inside): mixed {
        $inside = true;

        try {
          return $action();
        } finally {
          $inside = false;
        }
      },
    );
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturnCallback(static function () use (&$inside, $request): ApprovalRequest {
      self::assertTrue($inside);

      return $request;
    });
    $requests->expects(self::once())->method('save')->with($request);
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch')->willReturnCallback(static function (object $event) use (&$inside, $now): void {
      self::assertTrue($inside);
      self::assertInstanceOf(ApprovalWithdrawnEvent::class, $event);
      self::assertSame('user', $event->decisionByUserId);
      self::assertSame('active-member', $event->decisionByMemberId);
      self::assertSame($now, $event->occurredAt);
    });
    $result = $this->handler($requests, $lock, $events, $now)(new WithdrawApprovalRequestCommand('org', self::ID, 'user', 'Duplicate request'));
    self::assertSame('withdrawn', $result->status);
    self::assertSame('Duplicate request', $result->decisionNote);
    self::assertSame($now, $result->decidedAt);
    self::assertNull($result->executedAt);
    self::assertFalse($inside);
  }

  /**
   * @return iterable<string, array{bool, ?string, string, class-string<Throwable>}>
   */
  public static function denials(): iterable
  {
    yield 'inactive author' => [false, 'member', 'user', ApprovalRequestNotFoundException::class];
    yield 'unresolved member' => [true, null, 'user', ApprovalRequestNotFoundException::class];
    yield 'active other member' => [true, 'member', 'other', ApprovalWithdrawalNotAllowedException::class];
  }

  /**
   * @param class-string<Throwable> $exception expected refusal
   */
  #[DataProvider('denials')]
  public function testCannotWithdrawOutsideActiveAuthorScope(bool $active, ?string $member, string $user, string $exception): void
  {
    $now = new DateTimeImmutable();
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->request($now));
    $requests->expects(self::never())->method('save');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $this->expectException($exception);
    $this->handler($requests, $this->lock(), $events, $now, $active, $member)(new WithdrawApprovalRequestCommand('org', self::ID, $user));
  }

  public function testExpiryCommitsBeforeRefusalEscapesTheLock(): void
  {
    $now = new DateTimeImmutable();
    $request = $this->request($now->modify('-2 days'));
    $requests = $this->createMock(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($request);
    $requests->expects(self::once())->method('save')->with($request);
    $lock = $this->createMock(ApprovalDecisionLockPort::class);
    $lock->expects(self::once())->method('synchronized')->willReturnCallback(static function (string $id, callable $action): mixed {
      $result = $action();
      self::assertInstanceOf(ApprovalRequestNotPendingException::class, $result);

      return $result;
    });

    try {
      $this->handler($requests, $lock, $this->createStub(EventDispatcherPort::class), $now)(new WithdrawApprovalRequestCommand('org', self::ID, 'user'));
      self::fail('Expired request must not be withdrawn.');
    } catch (ApprovalRequestNotPendingException) {
      self::assertSame('expired', $request->status()->value);
    }
  }

  public function testDomainRefusesAnotherUserEvenWithoutAHandler(): void
  {
    $now = new DateTimeImmutable();
    $this->expectException(ApprovalWithdrawalNotAllowedException::class);
    $this->request($now)->withdraw('member', 'other', null, $now);
  }

  private function request(DateTimeImmutable $now): ApprovalRequest
  {
    return ApprovalRequest::create(ApprovalRequestId::fromString(self::ID), 'org', 'equipment_decommission', 'subject', 'old-member', 'user', [], $now->modify('+1 day'), $now);
  }

  private function lock(): ApprovalDecisionLockPort
  {
    $lock = $this->createStub(ApprovalDecisionLockPort::class);
    $lock->method('synchronized')->willReturnCallback(static fn (string $id, callable $action): mixed => $action());

    return $lock;
  }

  private function handler(ApprovalRequestRepositoryPort $requests, ApprovalDecisionLockPort $lock, EventDispatcherPort $events, DateTimeImmutable $now, bool $active = true, ?string $member = 'active-member'): WithdrawApprovalRequestHandler
  {
    $members = $this->createStub(ApprovalMemberDirectoryPort::class);
    $members->method('resolveMemberId')->willReturn($member);
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('isMemberOf')->willReturn($active);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);

    return new WithdrawApprovalRequestHandler($requests, $members, $authorization, $events, $clock, $lock);
  }
}
