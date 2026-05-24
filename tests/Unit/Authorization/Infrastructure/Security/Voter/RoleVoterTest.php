<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Authorization\Infrastructure\Security\Voter\RoleVoter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Test RoleVoterTest.
 *
 * @category Voter Tests
 */
#[CoversClass(className: RoleVoter::class)]
final class RoleVoterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testVoteGrantsWhenRoleMatches(): void
  {
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasRole')
      ->with('user-123', 'admin')
      ->willReturn(true);

    $voter = new RoleVoter($authorization);
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn(new SecurityUser('user-123', 'user@example.com', 'secret'));

    $result = $voter->vote($token, null, ['ROLE_ADMIN']);

    self::assertSame(Voter::ACCESS_GRANTED, $result);
  }

  #[Test]
  public function testVoteAbstainsForUnsupportedAttribute(): void
  {
    $voter = new RoleVoter($this->createStub(AuthorizationPort::class));
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn(new SecurityUser('user-123', 'user@example.com', 'secret'));

    $result = $voter->vote($token, null, ['PERMISSION_READ']);

    self::assertSame(Voter::ACCESS_ABSTAIN, $result);
  }

  #[Test]
  public function testVoteDeniesWhenUserIsNotSecurityUser(): void
  {
    $voter = new RoleVoter($this->createStub(AuthorizationPort::class));
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn($this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class));

    $result = $voter->vote($token, null, ['ROLE_ADMIN']);

    self::assertSame(Voter::ACCESS_DENIED, $result);
  }
  // #endregion
}
