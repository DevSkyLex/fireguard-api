<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use Organization\Application\Port\Inbound\OrganizationJoinAccessPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Join\ReadOrganizationJoin\{ReadOrganizationJoinHandler, ReadOrganizationJoinQuery};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;

/** @category Test @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class ReadOrganizationJoinHandlerTest extends TestCase
{
  #[Test]
  public function unverifiedOAuthAddressCannotEnumerateInvitationsOrDomains(): void
  {
    $joins = $this->createMock(OrganizationJoinRepositoryPort::class);
    $joins->method('requests')->willReturn([]);
    $joins->expects(self::never())->method('domainsForName');
    $joins->expects(self::never())->method('invitationIds');
    $emails = $this->createStub(EmailOwnershipPort::class);
    $emails->method('get')->willReturn(new EmailOwnershipResult('actor', 'employee@corp.example', false));
    $handler = new ReadOrganizationJoinHandler($joins, $this->createStub(OrganizationJoinAccessPort::class), $this->createStub(OrganizationRepositoryPort::class), $this->createStub(OrganizationInvitationRepositoryPort::class), $this->createStub(OrganizationMemberRepositoryPort::class), $emails);
    self::assertSame(['emailProofRequired' => true, 'invitations' => [], 'organizations' => [], 'requests' => []], $handler(new ReadOrganizationJoinQuery('options', 'actor'))->data);
  }

  #[Test]
  public function ownRequestListDoesNotRequireDomainProof(): void
  {
    $joins = $this->createStub(OrganizationJoinRepositoryPort::class);
    $joins->method('requests')->willReturn([]);
    $emails = $this->createMock(EmailOwnershipPort::class);
    $emails->expects(self::never())->method('get');
    $handler = new ReadOrganizationJoinHandler($joins, $this->createStub(OrganizationJoinAccessPort::class), $this->createStub(OrganizationRepositoryPort::class), $this->createStub(OrganizationInvitationRepositoryPort::class), $this->createStub(OrganizationMemberRepositoryPort::class), $emails);
    self::assertSame(['member' => [], 'totalItems' => 0], $handler(new ReadOrganizationJoinQuery('requests', 'actor'))->data);
  }
}
