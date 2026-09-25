<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\ListApprovalRequests;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Application\UseCase\Query\Request\ListApprovalRequests\{ListApprovalRequestsHandler, ListApprovalRequestsQuery};
use Approval\Domain\Exception\{ApprovalAccessDeniedException, ApprovalRequestNotFoundException};
use Approval\Domain\Model\ApprovalRequest\{ApprovalRequest, ApprovalRequestCreation, ApprovalRequestSchedule, ApprovalRequestSubmission};
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Contract\Authorization\OrganizationAccessDecision;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test ListApprovalRequestsHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(ListApprovalRequestsHandler::class)]
final class ListApprovalRequestsHandlerTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  private const string ORG_ID = 'org-1';

  #[Test]
  public function testInvokeReturnsPagedResult(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('listByOrganization')->willReturn([$this->request()]);
    $requests->method('countByOrganization')->willReturn(1);

    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('resolveAccess')
      ->with('user-1', self::ORG_ID, 'organization.approvals.read')
      ->willReturn(OrganizationAccessDecision::GRANTED);

    $handler = new ListApprovalRequestsHandler(requests: $requests, authorization: $authorization, views: $this->views());

    $result = $handler(new ListApprovalRequestsQuery(self::ORG_ID, 'user-1', 'pending', 'nc_waiver', 1, 30));

    self::assertCount(1, $result->items);
    self::assertSame(self::REQUEST_ID, $result->items[0]->id);
    self::assertSame(1, $result->page);
    self::assertSame(30, $result->itemsPerPage);
    self::assertSame(1, $result->total);
  }

  #[Test]
  public function testInvokeClampsItemsPerPageToAtLeastOne(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('listByOrganization')->willReturn([]);
    $requests->method('countByOrganization')->willReturn(0);

    $result = $this->handler($requests)(new ListApprovalRequestsQuery(self::ORG_ID, 'user-1', null, null, 1, 0));

    self::assertSame([], $result->items);
    self::assertSame(1, $result->itemsPerPage);
    self::assertSame(0, $result->total);
  }

  #[Test]
  public function testInvokeThrowsAccessDeniedWhenMemberLacksTheReadPermission(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);

    $this->expectException(ApprovalAccessDeniedException::class);

    $this->handler($requests, OrganizationAccessDecision::MISSING_PERMISSION)(
      new ListApprovalRequestsQuery(self::ORG_ID, 'user-1', null, null, 1, 30),
    );
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenTheOrganizationIsOutsideTheCallersScope(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);

    // The same not-found an unknown organization identifier produces: a 403
    // here would tell an outsider that this organization is real.
    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler($requests, OrganizationAccessDecision::OUTSIDE_SCOPE)(
      new ListApprovalRequestsQuery(self::ORG_ID, 'user-1', null, null, 1, 30),
    );
  }

  private function request(): ApprovalRequest
  {
    return ApprovalRequest::create(new ApprovalRequestCreation(
      ApprovalRequestId::fromString(self::REQUEST_ID),
      self::ORG_ID,
      'nc_waiver',
      'nc-1',
      new ApprovalRequestSubmission('member-1', 'user-1', []),
      new ApprovalRequestSchedule(new DateTimeImmutable('2026-02-01T00:00:00+00:00'), new DateTimeImmutable('2026-01-18T00:00:00+00:00')),
    ));
  }

  private function views(): \Approval\Application\Service\ApprovalRequestViewFactory
  {
    $clock = $this->createStub(\Shared\Application\Port\Outbound\ClockPort::class);
    $clock->method('now')->willReturn(new DateTimeImmutable('2026-01-18T00:00:00Z'));

    return new \Approval\Application\Service\ApprovalRequestViewFactory(
      $this->createStub(OrganizationAuthorizationPort::class),
      $this->createStub(\Approval\Application\Port\Outbound\ApprovalPolicyPort::class),
      $this->createStub(\Approval\Application\Port\Outbound\ApprovalMemberDirectoryPort::class),
      $clock,
    );
  }

  private function handler(
    ApprovalRequestRepositoryPort $requests,
    OrganizationAccessDecision $decision = OrganizationAccessDecision::GRANTED,
  ): ListApprovalRequestsHandler {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('resolveAccess')->willReturn($decision);

    return new ListApprovalRequestsHandler(
      requests: $requests,
      authorization: $authorization,
      views: $this->views(),
    );
  }
}
