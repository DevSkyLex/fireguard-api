<?php

declare(strict_types=1);

namespace Tests\Unit\Authorization\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Authorization\Application\Port\Inbound\AuthorizationPort;
use Authorization\Infrastructure\Security\Voter\PermissionVoter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Test PermissionVoterTest.
 *
 * @category Voter Tests
 */
#[CoversClass(className: PermissionVoter::class)]
final class PermissionVoterTest extends TestCase
{
  // #region Tests
  #[Test]
  public function testVoteGrantsWhenPermissionMatches(): void
  {
    $authorization = $this->createMock(AuthorizationPort::class);
    $authorization->expects(self::once())
      ->method('hasPermission')
      ->with('user-123', 'users.create')
      ->willReturn(true);

    $voter = new PermissionVoter($authorization);
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn(new SecurityUser('user-123', 'user@example.com', 'secret'));

    $result = $voter->vote($token, null, ['users.create']);

    self::assertSame(Voter::ACCESS_GRANTED, $result);
  }

  #[Test]
  public function testVoteAbstainsForUnsupportedAttribute(): void
  {
    $voter = new PermissionVoter($this->createStub(AuthorizationPort::class));
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn(new SecurityUser('user-123', 'user@example.com', 'secret'));

    $result = $voter->vote($token, null, ['ROLE_ADMIN']);

    self::assertSame(Voter::ACCESS_ABSTAIN, $result);
  }

  #[Test]
  public function testVoteDeniesWhenUserIsNotSecurityUser(): void
  {
    $voter = new PermissionVoter($this->createStub(AuthorizationPort::class));
    $token = $this->createStub(TokenInterface::class);
    $token->method('getUser')
      ->willReturn($this->createStub(\Symfony\Component\Security\Core\User\UserInterface::class));

    $result = $voter->vote($token, null, ['users.create']);

    self::assertSame(Voter::ACCESS_DENIED, $result);
  }
  // #endregion
}
