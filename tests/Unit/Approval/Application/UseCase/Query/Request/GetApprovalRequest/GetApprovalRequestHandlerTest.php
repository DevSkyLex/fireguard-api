<?php

declare(strict_types=1);

namespace Tests\Unit\Approval\Application\UseCase\Query\Request\GetApprovalRequest;

use Approval\Application\Port\Outbound\ApprovalRequestRepositoryPort;
use Approval\Application\UseCase\Query\Request\GetApprovalRequest\{GetApprovalRequestHandler, GetApprovalRequestQuery};
use Approval\Domain\Exception\ApprovalRequestNotFoundException;
use Approval\Domain\Model\ApprovalRequest\ApprovalRequest;
use Approval\Domain\ValueObject\ApprovalRequestId;
use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;

/**
 * Test GetApprovalRequestHandler.
 *
 * @category Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(GetApprovalRequestHandler::class)]
final class GetApprovalRequestHandlerTest extends TestCase
{
  private const string REQUEST_ID = '018f0b68-6758-7a12-8a1d-3f0d97f64c01';

  private const string ORG_ID = 'org-1';

  #[Test]
  public function testInvokeReturnsResultWhenFound(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->request());

    $result = $this->handler($requests)(new GetApprovalRequestQuery(self::ORG_ID, self::REQUEST_ID, 'user-1'));

    self::assertSame(self::REQUEST_ID, $result->id);
    self::assertSame(self::ORG_ID, $result->organizationId);
    self::assertSame('pending', $result->status);
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenMissing(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn(null);

    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler($requests)(new GetApprovalRequestQuery(self::ORG_ID, self::REQUEST_ID, 'user-1'));
  }

  #[Test]
  public function testInvokeThrowsNotFoundWhenOrganizationMismatch(): void
  {
    $requests = $this->createStub(ApprovalRequestRepositoryPort::class);
    $requests->method('findById')->willReturn($this->request());

    $this->expectException(ApprovalRequestNotFoundException::class);

    $this->handler($requests)(new GetApprovalRequestQuery('other-org', self::REQUEST_ID, 'user-1'));
  }

  private function request(): ApprovalRequest
  {
    return ApprovalRequest::create(
      id: ApprovalRequestId::fromString(self::REQUEST_ID),
      organizationId: self::ORG_ID,
      actionType: 'nc_waiver',
      subjectId: 'nc-1',
      requestedByMemberId: 'member-1',
      requestedByUserId: 'user-1',
      payload: [],
      expiresAt: new DateTimeImmutable('2026-02-01T00:00:00+00:00'),
      now: new DateTimeImmutable('2026-01-18T00:00:00+00:00'),
    );
  }

  private function handler(ApprovalRequestRepositoryPort $requests): GetApprovalRequestHandler
  {
    return new GetApprovalRequestHandler(
      requests: $requests,
      authorization: $this->createStub(OrganizationAuthorizationPort::class),
    );
  }
}
