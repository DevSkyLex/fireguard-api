<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Command\Join\ManageOrganizationJoin;

use Organization\Application\Port\Inbound\{OrganizationJoinAccessPort, OrganizationPermissionGrantGuardPort, OrganizationQuotaPort};
use Organization\Application\Port\Outbound\{OrganizationDomainProofPort, OrganizationInvitationRepositoryPort, OrganizationJoinNotificationPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Command\Join\ManageOrganizationJoin\{ManageOrganizationJoinCommand, ManageOrganizationJoinHandler};
use Organization\Domain\Exception\OrganizationJoinException;
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\ValueObject\{OrganizationId, OrganizationName};
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
}
