<?php

declare(strict_types=1);

namespace Tests\Unit\Messaging\Application\Service;

use Messaging\Application\Port\Outbound\{MessagingMemberDirectoryPort, MessagingParticipantRepositoryPort};
use Messaging\Application\Service\MessagingAccessPolicy;
use Messaging\Domain\Exception\{MessagingAccessDeniedException, MessagingNotFoundException};
use Organization\Application\Port\Inbound\OrganizationAuthorizationPort;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Test MessagingAccessPolicyTest.
 *
 * @category Service Tests
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(MessagingAccessPolicy::class)]
final class MessagingAccessPolicyTest extends TestCase
{
  #[Test]
  public function testAssertCanReadThreadChecksBothTheSubjectAndMessagingReadPermissions(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', 'org-1', ['organization.facilities.read', 'organization.messaging.read']);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy->assertCanReadThread('user-1', 'org-1', 'organization.facilities.read');
  }

  #[Test]
  public function testAssertCanWriteChecksBothTheSubjectAndMessagingWritePermissions(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', 'org-1', ['organization.equipment.read', 'organization.messaging.write']);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy->assertCanWrite('user-1', 'org-1', 'organization.equipment.read');
  }

  #[Test]
  public function testAssertCanUseMessagingChecksOnlyTheMessagingReadPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', 'org-1', ['organization.messaging.read']);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy->assertCanUseMessaging('user-1', 'org-1');
  }

  #[Test]
  public function testAssertCanManageChecksOnlyTheMessagingManagePermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', 'org-1', ['organization.messaging.manage']);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy->assertCanManage('user-1', 'org-1');
  }

  #[Test]
  public function testResolveActiveMemberIdReturnsTheResolvedId(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn('member-1');

    $policy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    self::assertSame('member-1', $policy->resolveActiveMemberId('org-1', 'user-1'));
  }

  #[Test]
  public function testResolveActiveMemberIdAnswersNotFoundWhenNotAnActiveMember(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $policy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    // Not 403: every Messaging handler loads its record by global id and then
    // lands here, so a "forbidden" would confirm the record exists to a caller
    // from another organization — an id-enumeration oracle across tenants.
    $this->expectException(MessagingNotFoundException::class);

    $policy->resolveActiveMemberId('org-1', 'user-1');
  }

  #[Test]
  public function testResolveActiveMemberIdLeaksNothingAboutTheRecordItGuards(): void
  {
    $members = $this->createStub(MessagingMemberDirectoryPort::class);
    $members->method('resolveActiveMemberId')->willReturn(null);

    $policy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $members, $this->createStub(MessagingParticipantRepositoryPort::class));

    try {
      $policy->resolveActiveMemberId('org-1', 'user-1');
      self::fail('Expected a not-found exception.');
    } catch (MessagingNotFoundException $exception) {
      self::assertStringNotContainsString('org-1', $exception->getMessage());
      self::assertStringNotContainsString('user-1', $exception->getMessage());
    }
  }

  #[Test]
  public function testAssertCanReadChannelGrantsAParticipant(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $participants);

    $policy->assertCanReadChannel('user-1', 'org-1', 'channel-1', 'member-1');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanReadChannelDeniesANonParticipantWithoutManage(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $participants);

    $this->expectException(MessagingAccessDeniedException::class);

    $policy->assertCanReadChannel('user-1', 'org-1', 'channel-1', 'member-1');
  }

  #[Test]
  public function testAssertCanReadChannelAllowsAManagerBypassForANonParticipant(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $participants);

    $policy->assertCanReadChannel('user-1', 'org-1', 'channel-1', 'member-1');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanWriteChannelGrantsAParticipant(): void
  {
    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(true);

    $policy = new MessagingAccessPolicy($this->createStub(OrganizationAuthorizationPort::class), $this->createStub(MessagingMemberDirectoryPort::class), $participants);

    $policy->assertCanWriteChannel('user-1', 'org-1', 'channel-1', 'member-1');

    self::addToAssertionCount(1);
  }

  #[Test]
  public function testAssertCanWriteChannelDeniesANonParticipantEvenWithManage(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(true);

    $participants = $this->createStub(MessagingParticipantRepositoryPort::class);
    $participants->method('isParticipant')->willReturn(false);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $participants);

    $this->expectException(MessagingAccessDeniedException::class);

    $policy->assertCanWriteChannel('user-1', 'org-1', 'channel-1', 'member-1');
  }

  #[Test]
  public function testAssertCanManageChannelsChecksOnlyTheMessagingManagePermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('assertGrantedPermissions')
      ->with('user-1', 'org-1', ['organization.messaging.manage']);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    $policy->assertCanManageChannels('user-1', 'org-1');
  }

  #[Test]
  public function testHasReadPermissionChecksTheMessagingReadPermissionWithoutThrowing(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'org-1', 'organization.messaging.read')
      ->willReturn(true);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    self::assertTrue($policy->hasReadPermission('user-1', 'org-1'));
  }

  #[Test]
  public function testHasReadPermissionReturnsFalseWhenNotGranted(): void
  {
    $authorization = $this->createStub(OrganizationAuthorizationPort::class);
    $authorization->method('hasPermission')->willReturn(false);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    self::assertFalse($policy->hasReadPermission('user-1', 'org-1'));
  }

  #[Test]
  public function testHasPermissionDelegatesToTheAuthorizationPortForAnArbitraryPermission(): void
  {
    /** @var OrganizationAuthorizationPort&MockObject $authorization */
    $authorization = $this->createMock(OrganizationAuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-1', 'org-1', 'organization.facilities.read')
      ->willReturn(true);

    $policy = new MessagingAccessPolicy($authorization, $this->createStub(MessagingMemberDirectoryPort::class), $this->createStub(MessagingParticipantRepositoryPort::class));

    self::assertTrue($policy->hasPermission('user-1', 'org-1', 'organization.facilities.read'));
  }
}
