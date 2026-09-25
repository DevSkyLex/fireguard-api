<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\Service;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use Organization\Application\Port\Outbound\{OrganizationJoinRepositoryPort, OrganizationRepositoryPort, OrganizationRoleRepositoryPort};
use Organization\Application\Service\OrganizationJoinAccessService;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode, OrganizationName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;

final class OrganizationJoinAccessServiceTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655444501';

  #[Test]
  public function verifiedApplicantCanCancelAndManagerCanApproveActiveDomainRequest(): void
  {
    $createdAt = new DateTimeImmutable('-1 hour');
    $request = new OrganizationJoinRequest('request', self::ORGANIZATION_ID, 'applicant', 'employee@corp.example', 'proof', $createdAt, new DateTimeImmutable('+1 hour'));
    $domain = new OrganizationDomain('proof', self::ORGANIZATION_ID, 'corp.example', 'challenge', 'verified', new DateTimeImmutable('-1 minute'));
    $joins = $this->createStub(OrganizationJoinRepositoryPort::class);
    $joins->method('policy')->willReturn(new OrganizationAccessPolicy(self::ORGANIZATION_ID, OrganizationJoinMode::APPROVAL_REQUIRED));
    $joins->method('domains')->willReturn([$domain]);
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn(Organization::create(new OrganizationId(self::ORGANIZATION_ID), new OrganizationName('Example'), 'owner'));
    $emails = $this->createStub(EmailOwnershipPort::class);
    $emails->method('get')->willReturn(new EmailOwnershipResult('applicant', 'employee@corp.example', true, verifiedAt: $createdAt->modify('-1 minute')));
    $service = new OrganizationJoinAccessService($joins, $organizations, $this->createStub(OrganizationRoleRepositoryPort::class), $this->createStub(OrganizationAuthorizationPort::class), $emails);

    self::assertSame(['cancel'], $service->requestView($request, 'applicant')['actions']);
    $managerView = $service->requestView($request, 'manager', true);
    self::assertSame('pending', $managerView['status']);
    self::assertSame('employee@corp.example', $managerView['applicantEmail']);
    self::assertSame(['approve', 'reject'], $managerView['actions']);
  }

  #[Test]
  public function unavailableEmailOwnershipCancelsPendingRequestWithoutApprovalActions(): void
  {
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::never())->method('policy');
    $joins->expects(self::never())->method('domains');
    $emails = $this->createStub(EmailOwnershipPort::class);
    $emails->method('get')->willThrowException(new RuntimeException('email_ownership_unavailable'));
    $service = new OrganizationJoinAccessService($joins, $this->createStub(OrganizationRepositoryPort::class), $this->createStub(OrganizationRoleRepositoryPort::class), $this->createStub(OrganizationAuthorizationPort::class), $emails);
    $request = new OrganizationJoinRequest('request', self::ORGANIZATION_ID, 'applicant', 'employee@corp.example', 'proof', new DateTimeImmutable('-1 hour'), new DateTimeImmutable('+1 hour'));

    $view = $service->requestView($request, 'manager', true);
    self::assertSame('cancelled', $view['status']);
    self::assertSame([], $view['actions']);
  }
}
