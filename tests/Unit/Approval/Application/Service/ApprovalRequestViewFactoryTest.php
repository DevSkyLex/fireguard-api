<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\Service;

use Approval\Application\Contract\Policy\ApprovalPolicy;
use Approval\Application\Port\Outbound\{ApprovalMemberDirectoryPort, ApprovalPolicyPort};
use Approval\Application\Service\ApprovalRequestViewFactory;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Outbound\ClockPort;

final class ApprovalRequestViewFactoryTest extends TestCase
{
  /**
   * @return iterable<string, array{bool, bool, ?string, bool, bool, ?string}>
   */
  public static function eligibility(): iterable
  {
    yield 'authorized colleague' => [true, true, 'colleague', false, false, null];
    yield 'permission missing' => [false, true, 'colleague', false, false, 'approval_permission_required'];
    yield 'membership removed' => [true, true, null, false, false, 'approval_permission_required'];
    yield 'role insufficient' => [true, false, 'colleague', false, false, 'approval_role_required'];
    yield 'self forbidden' => [true, true, 'requester', false, false, 'approval_self_decision_forbidden'];
    yield 'self allowed by policy' => [true, true, 'requester', true, false, null];
    yield 'deadline reached' => [true, true, 'colleague', false, true, 'approval_expired'];
  }

  #[DataProvider('eligibility')]
  public function testCapabilitiesReflectCurrentRightsPolicyAndDeadline(bool $permission, bool $role, ?string $member, bool $allowSelf, bool $expired, ?string $reason): void
  {
    $now = new DateTimeImmutable('2026-09-20T10:00:00Z');
    $request = ApprovalRequest::create(
      ApprovalRequestId::fromString('bc000000-0000-4000-8000-000000000097'),
      'org',
      'nc_waiver',
      'subject',
      'requester',
      'user',
      [],
      $expired ? $now : $now->modify('+1 day'),
      $now->modify('-1 day'),
    );
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn($permission);
    $policies = $this->createStub(ApprovalPolicyPort::class);
    $policies->method('policyFor')->willReturn(new ApprovalPolicy([], $allowSelf, 7));
    $members = $this->createStub(ApprovalMemberDirectoryPort::class);
    $members->method('resolveMemberId')->willReturn($member);
    $members->method('memberSatisfiesRole')->willReturn($role);
    $clock = $this->createStub(ClockPort::class);
    $clock->method('now')->willReturn($now);
    $factory = new ApprovalRequestViewFactory($authorization, $policies, $members, $clock);
    $view = $factory->forUser($request, 'reader');
    self::assertSame($reason, $view->decisionBlockReason);
    self::assertSame(null === $reason ? ['approve', 'reject'] : [], $view->allowedActions);
    $request->reject('colleague', 'other', null, $now);
    self::assertSame('approval_not_pending', $factory->forUser($request, 'reader')->decisionBlockReason);
    self::assertSame([], $factory->forUser($request, 'reader')->allowedActions);
  }
}
