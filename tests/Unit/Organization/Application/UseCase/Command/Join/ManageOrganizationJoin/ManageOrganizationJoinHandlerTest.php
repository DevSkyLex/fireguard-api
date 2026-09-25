<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Join\ManageOrganizationJoin;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\{OrganizationJoinAccessPort, OrganizationPermissionGrantGuardPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\{OrganizationDomainProofPort, OrganizationInvitationRepositoryPort, OrganizationJoinNotificationPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Join\ManageOrganizationJoin\{ManageOrganizationJoinCommand, ManageOrganizationJoinHandler};
use Organization\Domain\Exception\OrganizationJoinException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationJoinRequest};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode, OrganizationName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Shared\Application\Port\Inbound\CommandBusPort;
use Shared\Application\Port\Outbound\{EventDispatcherPort, LoggerPort, TransactionManagerPort};
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;

/** @category Test @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class ManageOrganizationJoinHandlerTest extends TestCase
{
  private const string ORG = '550e8400-e29b-41d4-a716-446655444501';

  #[Test]
  public function absentEmailProofRefusesMembershipBeforeDomainDiscoveryOrWrite(): void
  {
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::once())->method('lock')->with(self::ORG);
    $joins->expects(self::never())->method('saveRequest');
    $access = $this->createMock(OrganizationJoinAccessPort::class);
    $access->expects(self::never())->method('eligibleDomain');
    $orgs = $this->createStub(OrganizationRepositoryPort::class);
    $orgs->method('findById')->willReturn(Organization::create(new OrganizationId(self::ORG), new OrganizationName('Example'), 'owner'));
    $emails = $this->createStub(EmailOwnershipPort::class);
    $emails->method('get')->willReturn(new EmailOwnershipResult('actor', 'employee@corp.example', false));
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $handler = new ManageOrganizationJoinHandler($joins, $this->createStub(OrganizationDomainProofPort::class), $access, $orgs, $this->createStub(OrganizationMemberRepositoryPort::class), $this->createStub(OrganizationInvitationRepositoryPort::class), $emails, $this->createStub(OrganizationPermissionGrantGuardPort::class), $this->createStub(OrganizationQuotaPort::class), $transaction, $commands, $events, $this->createStub(OrganizationJoinNotificationPort::class), $this->createStub(LoggerPort::class));
    $this->expectException(OrganizationJoinException::class);
    $this->expectExceptionMessage('organization_join_email_proof_required');
    $handler(new ManageOrganizationJoinCommand('join', 'actor', self::ORG));
  }

  #[Test]
  public function unchangedInvitationOnlyPolicyIsSavedWithoutEmittingAChange(): void
  {
    $policy = new OrganizationAccessPolicy(self::ORG);
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::once())->method('lock')->with(self::ORG);
    $joins->method('policy')->willReturn($policy);
    $joins->expects(self::once())->method('savePolicy')->with($policy);
    $access = $this->createMock(OrganizationJoinAccessPort::class);
    $access->expects(self::once())->method('assertManage')->with('manager', self::ORG, true);
    $access->method('policyView')->willReturn(['mode' => OrganizationJoinMode::INVITATION_ONLY->value, 'roleId' => null, 'roleLabel' => null, 'domains' => [], 'eligibleRoles' => []]);
    $orgs = $this->createStub(OrganizationRepositoryPort::class);
    $orgs->method('findById')->willReturn(Organization::create(new OrganizationId(self::ORG), new OrganizationName('Example'), 'owner'));
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::never())->method('dispatch');
    $logger = $this->createMock(LoggerPort::class);
    $logger->expects(self::never())->method('info');
    $handler = new ManageOrganizationJoinHandler($joins, $this->createStub(OrganizationDomainProofPort::class), $access, $orgs, $this->createStub(OrganizationMemberRepositoryPort::class), $this->createStub(OrganizationInvitationRepositoryPort::class), $this->createStub(EmailOwnershipPort::class), $this->createStub(OrganizationPermissionGrantGuardPort::class), $this->createStub(OrganizationQuotaPort::class), $transaction, $this->createStub(CommandBusPort::class), $events, $this->createStub(OrganizationJoinNotificationPort::class), $logger);

    $result = $handler(new ManageOrganizationJoinCommand('policy', 'manager', self::ORG, mode: OrganizationJoinMode::INVITATION_ONLY->value));

    self::assertSame(OrganizationJoinMode::INVITATION_ONLY, $policy->mode);
    self::assertSame(OrganizationJoinMode::INVITATION_ONLY->value, $result->data['mode']);
  }

  #[Test]
  public function approvalWithChangedEmailCancelsPendingRequestBeforeReportingError(): void
  {
    $createdAt = new DateTimeImmutable('-2 days');
    $request = new OrganizationJoinRequest('request', self::ORG, 'applicant', 'original@corp.example', 'domain', $createdAt, $createdAt->modify('+30 days'));
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->expects(self::once())->method('lock')->with(self::ORG);
    $joins->method('request')->with('request')->willReturn($request);
    $joins->expects(self::once())->method('saveRequest')->with($request);
    $access = $this->createMock(OrganizationJoinAccessPort::class);
    $access->expects(self::once())->method('assertManage')->with('manager', self::ORG);
    $access->method('requestView')->willReturn([]);
    $orgs = $this->createStub(OrganizationRepositoryPort::class);
    $orgs->method('findById')->willReturn(Organization::create(new OrganizationId(self::ORG), new OrganizationName('Example'), 'owner'));
    $emails = $this->createMock(EmailOwnershipPort::class);
    $emails->expects(self::once())->method('get')->with('applicant')->willReturn(new EmailOwnershipResult('applicant', 'changed@corp.example', true, verifiedAt: new DateTimeImmutable('-1 day')));
    $transaction = $this->createStub(TransactionManagerPort::class);
    $transaction->method('transactional')->willReturnCallback(static fn (callable $operation) => $operation());
    $commands = $this->createMock(CommandBusPort::class);
    $commands->expects(self::never())->method('dispatch');
    $events = $this->createMock(EventDispatcherPort::class);
    $events->expects(self::once())->method('dispatch');
    $notifications = $this->createMock(OrganizationJoinNotificationPort::class);
    $notifications->expects(self::once())->method('send')->with('applicant', self::ORG, 'request', 'cancelled');
    $handler = new ManageOrganizationJoinHandler($joins, $this->createStub(OrganizationDomainProofPort::class), $access, $orgs, $this->createStub(OrganizationMemberRepositoryPort::class), $this->createStub(OrganizationInvitationRepositoryPort::class), $emails, $this->createStub(OrganizationPermissionGrantGuardPort::class), $this->createStub(OrganizationQuotaPort::class), $transaction, $commands, $events, $notifications, $this->createStub(LoggerPort::class));

    try {
      $handler(new ManageOrganizationJoinCommand('approve', 'manager', self::ORG, 'request', roleIds: ['role']));
      self::fail('A changed email must be reported after cancelling the request.');
    } catch (OrganizationJoinException $exception) {
      self::assertSame('organization_join_email_changed', $exception->getMessage());
    }
    self::assertSame('cancelled', $request->status);
    self::assertNotNull($request->decidedAt);
  }
}
