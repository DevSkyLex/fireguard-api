<?php

declare(strict_types=1);

namespace Tests\Unit\Organization\Application\UseCase\Query\Join\ReadOrganizationJoin;

use DateTimeImmutable;
use Organization\Application\Port\Inbound\OrganizationJoinAccessPort;
use Organization\Application\Port\Outbound\{OrganizationInvitationRepositoryPort, OrganizationJoinRepositoryPort, OrganizationMemberRepositoryPort, OrganizationRepositoryPort};
use Organization\Application\UseCase\Query\Join\ReadOrganizationJoin\{ReadOrganizationJoinHandler, ReadOrganizationJoinQuery};
use Organization\Domain\Model\Organization\Organization;
use Organization\Domain\Model\OrganizationJoin\{OrganizationAccessPolicy, OrganizationDomain};
use Organization\Domain\ValueObject\{OrganizationId, OrganizationJoinMode, OrganizationName};
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use User\Application\Contract\EmailOwnershipResult;
use User\Application\Port\Inbound\EmailOwnershipPort;

/** @category Test @version 1.0.0 @author Valentin FORTIN <contact@valentin-fortin.pro> */
final class ReadOrganizationJoinHandlerTest extends TestCase
{
  private const string ORGANIZATION_ID = '550e8400-e29b-41d4-a716-446655444501';

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

  #[Test]
  public function verifiedDomainOffersAutomaticJoinWithEligibleRole(): void
  {
    $domain = new OrganizationDomain('proof', self::ORGANIZATION_ID, 'corp.example', 'challenge', 'verified', new DateTimeImmutable());
    $organization = Organization::create(new OrganizationId(self::ORGANIZATION_ID), new OrganizationName('Example'), 'owner');
    $joins = $this->createStub(OrganizationJoinRepositoryPort::class);
    $joins->method('requests')->willReturn([]);
    $joins->method('invitationIds')->willReturn([]);
    $joins->method('domainsForName')->willReturn([$domain]);
    $joins->method('policy')->willReturn(new OrganizationAccessPolicy(self::ORGANIZATION_ID, OrganizationJoinMode::AUTOMATIC, 'role-id'));
    $organizations = $this->createStub(OrganizationRepositoryPort::class);
    $organizations->method('findById')->willReturn($organization);
    $access = $this->createMock(OrganizationJoinAccessPort::class);
    $access->expects(self::once())->method('assertEligibleRole')->with(self::ORGANIZATION_ID, 'role-id')->willReturn('Member');
    $emails = $this->createStub(EmailOwnershipPort::class);
    $emails->method('get')->willReturn(new EmailOwnershipResult('actor', 'employee@corp.example', true));
    $handler = new ReadOrganizationJoinHandler($joins, $access, $organizations, $this->createStub(OrganizationInvitationRepositoryPort::class), $this->createStub(OrganizationMemberRepositoryPort::class), $emails);

    self::assertSame([
      'emailProofRequired' => false,
      'invitations' => [],
      'organizations' => [[
        'id' => self::ORGANIZATION_ID,
        'name' => 'Example',
        'logoUrl' => null,
        'domain' => 'corp.example',
        'roleLabel' => 'Member',
        'actions' => ['join'],
      ]],
      'requests' => [],
    ], $handler(new ReadOrganizationJoinQuery('options', 'actor'))->data);
  }
}
