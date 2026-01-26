<?php

declare(strict_types=1);

namespace Tests\Unit\Auth\Infrastructure\Security\Voter;

use Auth\Infrastructure\Security\User\SecurityUser;
use Auth\Infrastructure\Security\Voter\ResourceOwnerVoter;
use PHPUnit\Framework\Attributes\{CoversClass, Test};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Test ResourceOwnerVoterTest.
 *
 * @category Unit Test
 *
 * @author Valentin FORTIN <contact@valentin-fortin.pro>
 */
#[CoversClass(className: ResourceOwnerVoter::class)]
final class ResourceOwnerVoterTest extends TestCase
{
  // #region Methods
  /**
   * Method testVoteGrantsWhenOwnerMatches.
   */
  #[Test]
  public function testVoteGrantsWhenOwnerMatches(): void
  {
    $voter = new ResourceOwnerVoter();

    $subject = new class () {
      public function getUserId(): string
      {
        return 'user-123';
      }
    };

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  /**
   * Method testVoteDeniesWhenOwnerDoesNotMatch.
   */
  #[Test]
  public function testVoteDeniesWhenOwnerDoesNotMatch(): void
  {
    $voter = new ResourceOwnerVoter();

    $subject = new class () {
      public function getOwnerId(): string
      {
        return 'user-999';
      }
    };

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::VIEW_OWN]);

    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteDeniesWhenUserIsNotSecurityUser.
   */
  #[Test]
  public function testVoteDeniesWhenUserIsNotSecurityUser(): void
  {
    $voter = new ResourceOwnerVoter();
    $subject = new class () {
      public function getUserId(): string
      {
        return 'user-123';
      }
    };

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($this->createMock(UserInterface::class));

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::EDIT_OWN]);

    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  /**
   * Method testVoteUsesValueObjectOwnerId.
   */
  #[Test]
  public function testVoteUsesValueObjectOwnerId(): void
  {
    $voter = new ResourceOwnerVoter();

    $subject = new class () {
      public function ownerId(): object
      {
        return new class () {
          public string $value = 'user-123';
        };
      }
    };

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::DELETE_OWN]);

    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  #[Test]
  public function testVoteUsesOwnerObjectToString(): void
  {
    $voter = new ResourceOwnerVoter();

    $subject = new class () {
      public function getOwner(): object
      {
        return new class () {
          public function __toString(): string
          {
            return 'user-123';
          }
        };
      }
    };

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(Voter::ACCESS_GRANTED, $result);
  }

  #[Test]
  public function testVoteDeniesWhenOwnerIdCannotBeResolved(): void
  {
    $voter = new ResourceOwnerVoter();

    $subject = new class () {
      public function getOwnerId(): object
      {
        return new class () {};
      }
    };

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, $subject, [ResourceOwnerVoter::OWNER]);

    $this->assertSame(Voter::ACCESS_DENIED, $result);
  }

  #[Test]
  public function testVoteAbstainsWhenSubjectNotObject(): void
  {
    $voter = new ResourceOwnerVoter();

    $user = new SecurityUser(
      id: 'user-123',
      email: 'user@example.com',
      password: 'hash',
    );

    $token = $this->createMock(TokenInterface::class);
    $token->method('getUser')->willReturn($user);

    $result = $voter->vote($token, 'not-object', [ResourceOwnerVoter::OWNER]);

    $this->assertSame(Voter::ACCESS_ABSTAIN, $result);
  }
  // #endregion
}
